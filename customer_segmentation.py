import pandas as pd
import numpy as np
import mysql.connector
import matplotlib.pyplot as plt
from datetime import datetime
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import DBSCAN

# ✅ Connect to MySQL Database
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="retail_db"
)
cursor = conn.cursor()

# ✅ Drop & Recreate Tables to Ensure Correct Structure
cursor.execute("DROP TABLE IF EXISTS customer_rfm_analysis")
cursor.execute("DROP TABLE IF EXISTS customer_lrfmp_analysis")

cursor.execute("""
    CREATE TABLE customer_rfm_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT UNIQUE NOT NULL,
        Recency INT NOT NULL,
        Frequency INT NOT NULL,
        Monetary DECIMAL(10,2) NOT NULL,
        R INT,
        F INT,
        M INT,
        RFMGroup VARCHAR(10),
        RFMScore INT,
        Cluster INT  
    )
""")

cursor.execute("""
    CREATE TABLE customer_lrfmp_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT UNIQUE NOT NULL,
        Length INT NOT NULL,
        Recency INT NOT NULL,
        Frequency INT NOT NULL,
        Monetary DECIMAL(10,2) NOT NULL,
        Periodicity DECIMAL(10,2) NOT NULL,
        L INT,
        R INT,
        F INT,
        M INT,
        P INT,
        LRFMPGroup VARCHAR(10),
        LRFMPScore INT,
        Cluster INT
    )
""")

# ✅ Fetch Customer Data from Cleaned Transactions
cursor.execute("""
    SELECT customer_id, MIN(invoice_date) AS first_purchase, 
           MAX(invoice_date) AS last_purchase, COUNT(DISTINCT invoice_no) AS frequency, 
           SUM(quantity * unit_price) AS monetary 
    FROM cleaned_transactions GROUP BY customer_id
""")
data = cursor.fetchall()

columns = ["customer_id", "first_purchase", "last_purchase", "frequency", "monetary"]
df = pd.DataFrame(data, columns=columns)

# ✅ Convert Data Types
df["monetary"] = df["monetary"].astype(float)
df["frequency"] = df["frequency"].astype(int)

# ✅ Set Reference Date for Recency Calculation (Last Invoice Date: 2011-12-09)
reference_date = datetime(2011, 12, 10)

# ✅ Compute Recency, Length, Periodicity
df["first_purchase"] = pd.to_datetime(df["first_purchase"])
df["last_purchase"] = pd.to_datetime(df["last_purchase"])
df["recency"] = (reference_date - df["last_purchase"]).dt.days
df["length"] = (df["last_purchase"] - df["first_purchase"]).dt.days
df["periodicity"] = df.apply(lambda row: row["length"] / row["frequency"] if row["frequency"] > 0 else 0, axis=1)

df.dropna(inplace=True)

# ✅ Compute RFM & LRFMP Quantiles for Scoring (Recomputed from Non-Null Data)
rfm_quantiles = df[["recency", "frequency", "monetary"]].quantile(q=[0.25, 0.5, 0.75])
lrfmp_quantiles = df[["length", "recency", "frequency", "monetary", "periodicity"]].quantile(q=[0.25, 0.5, 0.75])

# ✅ Debug Quantiles
print("🔍 RFM Quantiles:\n", rfm_quantiles)
print("🔍 LRFMP Quantiles:\n", lrfmp_quantiles)

# ✅ Function for Recency: Lower is better (More recent → Higher score)
def RScore(x, p, d):
    if x <= d[p][0.25]: return 4
    elif x <= d[p][0.5]: return 3
    elif x <= d[p][0.75]: return 2
    else: return 1

# ✅ Function for Frequency & Monetary: Higher is better (More frequent → Higher score)
def FnMScore(x, p, d):
    if x <= d[p][0.25]: return 1  # Very low frequency or spending
    elif x <= d[p][0.5]: return 2
    elif x <= d[p][0.75]: return 3
    else: return 4  # High frequency or spending




# Assign Corrected Scores
# ✅ Apply scoring to RFM
df["R"] = df["recency"].apply(RScore, args=("recency", rfm_quantiles.to_dict()))
df["F"] = df["frequency"].apply(FnMScore, args=("frequency", rfm_quantiles.to_dict()))
df["M"] = df["monetary"].apply(FnMScore, args=("monetary", rfm_quantiles.to_dict()))

# ✅ Apply scoring to LRFMP
df["L"] = df["length"].apply(FnMScore, args=("length", lrfmp_quantiles,))
df["P"] = df["periodicity"].apply(FnMScore, args=("periodicity", lrfmp_quantiles,))

df["RFMGroup"] = df.apply(lambda row: f"{row['R']}{row['F']}{row['M']}", axis=1)
df["RFMScore"] = df[["R", "F", "M"]].sum(axis=1)

df["LRFMPGroup"] = df.apply(lambda row: f"{row['L']}{row['R']}{row['F']}{row['M']}{row['P']}", axis=1)
df["LRFMPScore"] = df[["L", "R", "F", "M", "P"]].sum(axis=1)


# ✅ Log Transform for Scaling
df["Recency_log"] = np.log(df["recency"] + 1)
df["Frequency_log"] = np.log(df["frequency"] + 1)
df["Monetary_log"] = np.log(df["monetary"] + 1)
df["Periodicity_log"] = np.log(df["periodicity"] + 1)

# ✅ Standardize Data
scaler = StandardScaler()
X_rfm = scaler.fit_transform(df[["Recency_log", "Frequency_log", "Monetary_log"]])
X_lrfmp = scaler.fit_transform(df[["length", "Recency_log", "Frequency_log", "Monetary_log", "Periodicity_log"]])

# ✅ Apply DBSCAN with Optimized Parameters
best_eps = 1.2  
best_min_samples = 5  

dbscan_rfm = DBSCAN(eps=best_eps, min_samples=best_min_samples)
dbscan_lrfmp = DBSCAN(eps=best_eps + 0.3, min_samples=best_min_samples)  

df["RFMCluster"] = dbscan_rfm.fit_predict(X_rfm)
df["LRFMPCluster"] = dbscan_lrfmp.fit_predict(X_lrfmp)

# ✅ Replace Noise (-1) with "Noise" Label
df["RFMCluster"] = df["RFMCluster"].apply(lambda x: "Noise" if x == -1 else x)
df["LRFMPCluster"] = df["LRFMPCluster"].apply(lambda x: "Noise" if x == -1 else x)

# ✅ Store Data in MySQL
cursor.execute("DELETE FROM customer_rfm_analysis")
cursor.execute("DELETE FROM customer_lrfmp_analysis")

for _, row in df.iterrows():
    cursor.execute("""
        INSERT INTO customer_rfm_analysis 
        (CustomerID, Recency, Frequency, Monetary, R, F, M, RFMGroup, RFMScore, Cluster)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """, (row.customer_id, row.recency, row.frequency, row.monetary, row.R, row.F, row.M, row.RFMGroup, row.RFMScore, row.RFMCluster))

    cursor.execute("""
        INSERT INTO customer_lrfmp_analysis 
        (CustomerID, Length, Recency, Frequency, Monetary, Periodicity, 
         L, R, F, M, P, LRFMPGroup, LRFMPScore, Cluster)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """, (row.customer_id, row.length, row.recency, row.frequency, row.monetary, row.periodicity,
          row.L, row.R, row.F, row.M, row.P, row.LRFMPGroup, row.LRFMPScore, row.LRFMPCluster))

conn.commit()
cursor.close()
conn.close()
print("✅ RFM & LRFMP Analysis with DBSCAN Completed!")
