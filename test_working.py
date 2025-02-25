import pandas as pd
import numpy as np
import datetime as dt
import math
import mysql.connector

from sklearn.preprocessing import StandardScaler
from sklearn.cluster import DBSCAN

#######################################
# 1) Connect & Read from MySQL
#######################################
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",  # <--- Your password
    database="retail_db",
)
cursor = conn.cursor()

SQL = """
SELECT 
    invoice_no,
    invoice_date,
    customer_id,
    quantity,
    unit_price,
    total_price,
    country
FROM cleaned_transactions
WHERE customer_id IS NOT NULL
"""

# Load data into df
df = pd.read_sql(SQL, conn)
print("Initial shape:", df.shape)
print(df.head(3))

# Close this read-only connection
cursor.close()
conn.close()

#######################################
# 2) Basic Cleaning in DataFrame
#######################################
df.rename(
    columns={
        "invoice_no": "InvoiceNo",
        "invoice_date": "InvoiceDate",
        "customer_id": "CustomerID",
        "quantity": "Quantity",
        "unit_price": "UnitPrice",
        "total_price": "total_amt",
    },
    inplace=True,
)

df["InvoiceDate"] = pd.to_datetime(df["InvoiceDate"], errors="coerce")

# Drop duplicates, remove null Customer/Date if needed
df.drop_duplicates(inplace=True)
df.dropna(subset=["CustomerID", "InvoiceDate"], inplace=True)

# Remove zero or negative quantity/price
df = df[(df["Quantity"] > 0) & (df["UnitPrice"] > 0)]

# (Optional) Keep only United Kingdom
df = df[df["country"] == "United Kingdom"].copy()

# If total_amt not guaranteed, recalc:
# df["total_amt"] = df["Quantity"] * df["UnitPrice"]

print("After cleaning:", df.shape)
print(df.head(3))

#######################################
# 3) Reconnect to MySQL,
#    Drop & Create Final Tables
#######################################
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",  # <--- Your password
    database="retail_db",
)
cursor = conn.cursor()

# Drop old tables if they exist
cursor.execute("DROP TABLE IF EXISTS customer_rfm_analysis")
cursor.execute("DROP TABLE IF EXISTS customer_lrfmp_analysis")

# Create RFM table
cursor.execute(
    """
    CREATE TABLE customer_rfm_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT,
        
        Recency FLOAT,
        Frequency FLOAT,
        Monetary FLOAT,
        
        Recency_log FLOAT,
        Frequency_log FLOAT,
        Monetary_log FLOAT,
        
        RFM_DBSCAN_Cluster INT,
        RFM_Outlier_Flag VARCHAR(20),
        
        RF_DBSCAN_Cluster INT,
        RF_Outlier_Flag VARCHAR(20),
        
        RM_DBSCAN_Cluster INT,
        RM_Outlier_Flag VARCHAR(20),
        
        FM_DBSCAN_Cluster INT,
        FM_Outlier_Flag VARCHAR(20)
    )
    """
)

# Create LRFMP table
cursor.execute(
    """
    CREATE TABLE customer_lrfmp_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT,
        
        Length FLOAT,
        Recency FLOAT,
        Frequency FLOAT,
        Monetary FLOAT,
        Periodicity FLOAT,
        
        Length_log FLOAT,
        Recency_log FLOAT,
        Frequency_log FLOAT,
        Monetary_log FLOAT,
        Periodicity_log FLOAT,
        
        -- Full LRFMP DBSCAN
        LRFMP_DBSCAN_Cluster INT,
        LRFMP_Outlier_Flag VARCHAR(20),
        
        -- L-R
        LR_DBSCAN_Cluster INT,
        LR_Outlier_Flag VARCHAR(20),
        
        -- L-F
        LF_DBSCAN_Cluster INT,
        LF_Outlier_Flag VARCHAR(20),
        
        -- L-M
        LM_DBSCAN_Cluster INT,
        LM_Outlier_Flag VARCHAR(20),
        
        -- F-P
        FP_DBSCAN_Cluster INT,
        FP_Outlier_Flag VARCHAR(20),
        
        -- M-P
        MP_DBSCAN_Cluster INT,
        MP_Outlier_Flag VARCHAR(20)
    )
    """
)

#######################################
# 4) Build RFM
#######################################
latest_date = df["InvoiceDate"].max() + pd.Timedelta(days=1)

rfm_df = (
    df.groupby("CustomerID")
    .agg(
        {
            "InvoiceDate": lambda x: (latest_date - x.max()).days,  # Recency
            "InvoiceNo": "nunique",  # Frequency
            "total_amt": "sum",      # Monetary
        }
    )
    .reset_index()
)

rfm_df.rename(
    columns={
        "InvoiceDate": "Recency",
        "InvoiceNo": "Frequency",
        "total_amt": "Monetary",
    },
    inplace=True,
)

print("RFM shape:", rfm_df.shape)
print(rfm_df.head(3))

#######################################
# 5) Build LRFMP
#######################################
lrfmp_temp = df.groupby("CustomerID").agg(
    {
        "InvoiceDate": [lambda x: x.min(), lambda x: x.max()],
        "InvoiceNo": "nunique",
        "total_amt": "sum",
    }
)
lrfmp_temp.columns = ["first_purchase", "last_purchase", "Frequency", "Monetary"]
lrfmp_temp.reset_index(inplace=True)

lrfmp_temp["Recency"] = (latest_date - lrfmp_temp["last_purchase"]).dt.days
lrfmp_temp["Length"] = (
    lrfmp_temp["last_purchase"] - lrfmp_temp["first_purchase"]
).dt.days
lrfmp_temp["Periodicity"] = lrfmp_temp.apply(
    lambda row: row["Length"] / row["Frequency"] if row["Frequency"] > 0 else 0, axis=1
)
lrfmp_temp.drop(["first_purchase", "last_purchase"], axis=1, inplace=True)

#######################################
# 6) Handle Nonpositive -> 1, then Log
#######################################
def handle_nonpositive(x):
    return x if x > 0 else 1

# RFM
for col in ["Recency", "Frequency", "Monetary"]:
    rfm_df[col] = rfm_df[col].apply(handle_nonpositive)

rfm_df["Recency_log"] = np.log(rfm_df["Recency"])
rfm_df["Frequency_log"] = np.log(rfm_df["Frequency"])
rfm_df["Monetary_log"] = np.log(rfm_df["Monetary"])

# LRFMP
lrfmp_df = lrfmp_temp.copy()
for col in ["Length", "Recency", "Frequency", "Monetary", "Periodicity"]:
    lrfmp_df[col] = lrfmp_df[col].apply(handle_nonpositive)

lrfmp_df["Length_log"] = np.log(lrfmp_df["Length"])
lrfmp_df["Recency_log"] = np.log(lrfmp_df["Recency"])
lrfmp_df["Frequency_log"] = np.log(lrfmp_df["Frequency"])
lrfmp_df["Monetary_log"] = np.log(lrfmp_df["Monetary"])
lrfmp_df["Periodicity_log"] = np.log(lrfmp_df["Periodicity"])

#######################################
# 7) DBSCAN on Full RFM
#######################################
from sklearn.cluster import DBSCAN
from sklearn.preprocessing import StandardScaler

X_rfm = StandardScaler().fit_transform(
    rfm_df[["Recency_log", "Frequency_log", "Monetary_log"]]
)
dbscan_rfm = DBSCAN(eps=eps, min_samples=5)
rfm_df["RFM_DBSCAN_Cluster"] = dbscan_rfm.fit_predict(X_rfm)
rfm_df["RFM_Outlier_Flag"] = rfm_df["RFM_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

#######################################
# 8) DBSCAN on Full LRFMP
#######################################
X_lrfmp = StandardScaler().fit_transform(
    lrfmp_df[["Length_log", "Recency_log", "Frequency_log", "Monetary_log", "Periodicity_log"]]
)
dbscan_lrfmp = DBSCAN(eps=1.5, min_samples=5)
lrfmp_df["LRFMP_DBSCAN_Cluster"] = dbscan_lrfmp.fit_predict(X_lrfmp)
lrfmp_df["LRFMP_Outlier_Flag"] = lrfmp_df["LRFMP_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

#######################################
# 9) DBSCAN on 2D RFM combos
#######################################
# R-F
X_rf = StandardScaler().fit_transform(
    rfm_df[["Recency_log", "Frequency_log"]]
)
dbscan_rf = DBSCAN(eps=1.0, min_samples=5)
rfm_df["RF_DBSCAN_Cluster"] = dbscan_rf.fit_predict(X_rf)
rfm_df["RF_Outlier_Flag"] = rfm_df["RF_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

# R-M
X_rm = StandardScaler().fit_transform(
    rfm_df[["Recency_log", "Monetary_log"]]
)
dbscan_rm = DBSCAN(eps=1.0, min_samples=5)
rfm_df["RM_DBSCAN_Cluster"] = dbscan_rm.fit_predict(X_rm)
rfm_df["RM_Outlier_Flag"] = rfm_df["RM_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

# F-M
X_fm = StandardScaler().fit_transform(
    rfm_df[["Frequency_log", "Monetary_log"]]
)
dbscan_fm = DBSCAN(eps=1.0, min_samples=5)
rfm_df["FM_DBSCAN_Cluster"] = dbscan_fm.fit_predict(X_fm)
rfm_df["FM_Outlier_Flag"] = rfm_df["FM_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

#######################################
# 10) DBSCAN on 2D LRFMP combos
#######################################
# L-R
X_lr = StandardScaler().fit_transform(
    lrfmp_df[["Length_log", "Recency_log"]]
)
dbscan_lr = DBSCAN(eps=1.0, min_samples=5)
lrfmp_df["LR_DBSCAN_Cluster"] = dbscan_lr.fit_predict(X_lr)
lrfmp_df["LR_Outlier_Flag"] = lrfmp_df["LR_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

# L-F
X_lf = StandardScaler().fit_transform(
    lrfmp_df[["Length_log", "Frequency_log"]]
)
dbscan_lf = DBSCAN(eps=1.0, min_samples=5)
lrfmp_df["LF_DBSCAN_Cluster"] = dbscan_lf.fit_predict(X_lf)
lrfmp_df["LF_Outlier_Flag"] = lrfmp_df["LF_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

# L-M
X_lm = StandardScaler().fit_transform(
    lrfmp_df[["Length_log", "Monetary_log"]]
)
dbscan_lm = DBSCAN(eps=1.0, min_samples=5)
lrfmp_df["LM_DBSCAN_Cluster"] = dbscan_lm.fit_predict(X_lm)
lrfmp_df["LM_Outlier_Flag"] = lrfmp_df["LM_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

# F-P
X_fp = StandardScaler().fit_transform(
    lrfmp_df[["Frequency_log", "Periodicity_log"]]
)
dbscan_fp = DBSCAN(eps=1.0, min_samples=5)
lrfmp_df["FP_DBSCAN_Cluster"] = dbscan_fp.fit_predict(X_fp)
lrfmp_df["FP_Outlier_Flag"] = lrfmp_df["FP_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

# M-P
X_mp = StandardScaler().fit_transform(
    lrfmp_df[["Monetary_log", "Periodicity_log"]]
)
dbscan_mp = DBSCAN(eps=1.0, min_samples=5)
lrfmp_df["MP_DBSCAN_Cluster"] = dbscan_mp.fit_predict(X_mp)
lrfmp_df["MP_Outlier_Flag"] = lrfmp_df["MP_DBSCAN_Cluster"].apply(
    lambda c: "Outlier" if c == -1 else "Inlier"
)

#######################################
# 11) Insert Results
#######################################
# RFM Insert: 15 placeholders total
for _, row in rfm_df.iterrows():
    cursor.execute(
        """
        INSERT INTO customer_rfm_analysis (
            CustomerID,
            Recency, Frequency, Monetary,
            Recency_log, Frequency_log, Monetary_log,
            RFM_DBSCAN_Cluster, RFM_Outlier_Flag,
            RF_DBSCAN_Cluster, RF_Outlier_Flag,
            RM_DBSCAN_Cluster, RM_Outlier_Flag,
            FM_DBSCAN_Cluster, FM_Outlier_Flag
        )
        VALUES (
            %s, %s, %s, %s,
            %s, %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s
        )
        """,
        (
            int(row["CustomerID"]),
            float(row["Recency"]),
            float(row["Frequency"]),
            float(row["Monetary"]),

            float(row["Recency_log"]),
            float(row["Frequency_log"]),
            float(row["Monetary_log"]),

            int(row["RFM_DBSCAN_Cluster"]),
            str(row["RFM_Outlier_Flag"]),

            int(row["RF_DBSCAN_Cluster"]),
            str(row["RF_Outlier_Flag"]),

            int(row["RM_DBSCAN_Cluster"]),
            str(row["RM_Outlier_Flag"]),

            int(row["FM_DBSCAN_Cluster"]),
            str(row["FM_Outlier_Flag"]),
        ),
    )

# LRFMP Insert: 23 placeholders total
for _, row in lrfmp_df.iterrows():
    cursor.execute(
        """
        INSERT INTO customer_lrfmp_analysis (
            CustomerID,
            Length, Recency, Frequency, Monetary, Periodicity,
            Length_log, Recency_log, Frequency_log, Monetary_log, Periodicity_log,
            LRFMP_DBSCAN_Cluster, LRFMP_Outlier_Flag,
            LR_DBSCAN_Cluster, LR_Outlier_Flag,
            LF_DBSCAN_Cluster, LF_Outlier_Flag,
            LM_DBSCAN_Cluster, LM_Outlier_Flag,
            FP_DBSCAN_Cluster, FP_Outlier_Flag,
            MP_DBSCAN_Cluster, MP_Outlier_Flag
        )
        VALUES (
            %s, %s, %s, %s, %s, %s,
            %s, %s, %s, %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s
        )
        """,
        (
            int(row["CustomerID"]),
            float(row["Length"]),
            float(row["Recency"]),
            float(row["Frequency"]),
            float(row["Monetary"]),
            float(row["Periodicity"]),

            float(row["Length_log"]),
            float(row["Recency_log"]),
            float(row["Frequency_log"]),
            float(row["Monetary_log"]),
            float(row["Periodicity_log"]),

            int(row["LRFMP_DBSCAN_Cluster"]),
            str(row["LRFMP_Outlier_Flag"]),

            int(row["LR_DBSCAN_Cluster"]),
            str(row["LR_Outlier_Flag"]),

            int(row["LF_DBSCAN_Cluster"]),
            str(row["LF_Outlier_Flag"]),

            int(row["LM_DBSCAN_Cluster"]),
            str(row["LM_Outlier_Flag"]),

            int(row["FP_DBSCAN_Cluster"]),
            str(row["FP_Outlier_Flag"]),

            int(row["MP_DBSCAN_Cluster"]),
            str(row["MP_Outlier_Flag"]),
        ),
    )

conn.commit()
cursor.close()
conn.close()

print("✅ DBSCAN clustering (RFM + LRFMP + combos) inserted successfully!")
