import pandas as pd
import numpy as np
import mysql.connector
from datetime import datetime
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import KMeans, DBSCAN

# ✅ Connect to MySQL
conn = mysql.connector.connect(
    host="localhost", user="root", password="", database="retail_db"
)
cursor = conn.cursor()

# ✅ Drop & Recreate Tables
cursor.execute("DROP TABLE IF EXISTS customer_rfm_analysis")
cursor.execute("DROP TABLE IF EXISTS customer_lrfmp_analysis")

cursor.execute(
    """
    CREATE TABLE customer_rfm_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT UNIQUE NOT NULL,
        Recency INT NOT NULL,
        Frequency INT NOT NULL,
        Monetary FLOAT NOT NULL,
        R INT, F INT, M INT,
        RFMGroup VARCHAR(10),
        RFMScore INT,
        RFM_Cluster_RF INT,
        RFM_Cluster_FM INT,
        RFM_Cluster_RM INT,
        RFM_Cluster_RFM INT,
        RFM_DBSCAN_Segment VARCHAR(20)
    )
"""
)

cursor.execute(
    """
    CREATE TABLE customer_lrfmp_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT UNIQUE NOT NULL,
        Length INT NOT NULL,
        Recency INT NOT NULL,
        Frequency INT NOT NULL,
        Monetary FLOAT NOT NULL,
        Periodicity FLOAT NOT NULL,
        L INT, R INT, F INT, M INT, P INT,
        LRFMPGroup VARCHAR(10),
        LRFMPScore INT,
        LRFMP_Cluster_LRFMP INT,
        LRFMP_Cluster_LRFM INT,
        LRFMP_Cluster_LFP INT,
        LRFMP_Cluster_LFM INT,
        LRFMP_DBSCAN_Segment VARCHAR(20)
    )
"""
)

# ✅ Fetch Customer Data
cursor.execute(
    """
    SELECT customer_id, MIN(invoice_date) AS first_purchase, 
           MAX(invoice_date) AS last_purchase, COUNT(DISTINCT invoice_no) AS frequency, 
           SUM(quantity * unit_price) AS monetary
    FROM cleaned_transactions 
    WHERE customer_id IS NOT NULL 
    GROUP BY customer_id
"""
)
data = cursor.fetchall()

columns = ["customer_id", "first_purchase", "last_purchase", "frequency", "monetary"]
df = pd.DataFrame(data, columns=columns)

# ✅ Convert Data Types
df["monetary"] = df["monetary"].astype(float)
df["frequency"] = df["frequency"].astype(int)

# ✅ Convert first_purchase and last_purchase to datetime
df["first_purchase"] = pd.to_datetime(df["first_purchase"], errors="coerce")
df["last_purchase"] = pd.to_datetime(df["last_purchase"], errors="coerce")

# ✅ Drop rows where dates are missing
df.dropna(subset=["first_purchase", "last_purchase"], inplace=True)

# ✅ Compute Reference Date as Latest Last Purchase
reference_date = df["last_purchase"].max()

# ✅ Compute Recency, Length, Periodicity
df["recency"] = (reference_date - df["last_purchase"]).dt.days
df["length"] = (df["last_purchase"] - df["first_purchase"]).dt.days
df["periodicity"] = df.apply(
    lambda row: row["length"] / row["frequency"] if row["frequency"] > 0 else 0, axis=1
)

# ✅ Drop any remaining NaN values
df.dropna(
    subset=["length", "recency", "frequency", "monetary", "periodicity"], inplace=True
)

# ✅ Compute Quantiles for Scoring
quantiles = (
    df[["recency", "frequency", "monetary", "length", "periodicity"]]
    .quantile(q=[0.25, 0.5, 0.75])
    .to_dict()
)


# ✅ Use Your Scoring Functions
def RScore(x, p, d):
    if x <= d[p][0.25]:
        return 1
    elif x <= d[p][0.5]:
        return 2
    elif x <= d[p][0.75]:
        return 3
    else:
        return 4


def FScore(x, p, d):
    if x <= d[p][0.25]:
        return 4
    elif x <= d[p][0.5]:
        return 3
    elif x <= d[p][0.75]:
        return 2
    else:
        return 1


def MScore(x, p, d):
    if x <= d[p][0.25]:
        return 4
    elif x <= d[p][0.5]:
        return 3
    elif x <= d[p][0.75]:
        return 2
    else:
        return 1


# ✅ Assign Scores
df["R"] = df["recency"].apply(
    RScore,
    args=(
        "recency",
        quantiles,
    ),
)
df["F"] = df["frequency"].apply(
    FScore,
    args=(
        "frequency",
        quantiles,
    ),
)
df["M"] = df["monetary"].apply(
    MScore,
    args=(
        "monetary",
        quantiles,
    ),
)
df["L"] = df["length"].apply(
    MScore,
    args=(
        "length",
        quantiles,
    ),
)
df["P"] = df["periodicity"].apply(
    FScore,
    args=(
        "periodicity",
        quantiles,
    ),
)

# ✅ Generate RFM and LRFMP Grouping
df["RFMGroup"] = df.apply(lambda row: f"{row['R']}{row['F']}{row['M']}", axis=1)
df["RFMScore"] = df[["R", "F", "M"]].sum(axis=1)

df["LRFMPGroup"] = df.apply(
    lambda row: f"{row['L']}{row['R']}{row['F']}{row['M']}{row['P']}", axis=1
)
df["LRFMPScore"] = df[["L", "R", "F", "M", "P"]].sum(axis=1)

# ✅ Standardize Different Feature Combinations
scaler = StandardScaler()

X_rfm = scaler.fit_transform(df[["Recency_log", "Frequency_log", "Monetary_log"]])
X_rf = scaler.fit_transform(df[["Recency_log", "Frequency_log"]])
X_rm = scaler.fit_transform(df[["Recency_log", "Monetary_log"]])
X_fm = scaler.fit_transform(df[["Frequency_log", "Monetary_log"]])
X_lrfmp = scaler.fit_transform(
    df[["Length", "Recency", "Frequency", "Monetary", "Periodicity"]]
)

# ✅ Apply K-Means for Each Combination
kmeans_rfm = KMeans(n_clusters=3, random_state=42, n_init=10).fit(X_rfm)
kmeans_rf = KMeans(n_clusters=3, random_state=42, n_init=10).fit(X_rf)
kmeans_rm = KMeans(n_clusters=3, random_state=42, n_init=10).fit(X_rm)
kmeans_fm = KMeans(n_clusters=3, random_state=42, n_init=10).fit(X_fm)
kmeans_lrfmp = KMeans(n_clusters=3, random_state=42, n_init=10).fit(X_lrfmp)


########################
# Reorder F-M clusters
########################
#######################################
# HELPER FUNCTION: reorder_clusters_fm
#######################################
def reorder_clusters_fm(
    df, cluster_col="KMeans_FM_Cluster", freq_col="Frequency", mon_col="Monetary"
):
    """
    Re-label the KMeans_FM_Cluster so that:
      - Cluster 0 = lowest product of Frequency×Monetary (Low F, Low M)
      - Cluster 2 = highest product of Frequency×Monetary (High F, High M)
      - Cluster 1 = the one in between
    """
    # 1) Compute the raw average freq & mon for each cluster
    stats = df.groupby(cluster_col).agg({freq_col: "mean", mon_col: "mean"})

    # 2) For each cluster, define measure = (avg Frequency × avg Monetary)
    measure = {}
    for c in stats.index:
        freq_mean = stats.loc[c, freq_col]
        mon_mean = stats.loc[c, mon_col]
        measure[c] = freq_mean * mon_mean  # product of freq & monetary

    # 3) Sort by measure ascending
    #    The cluster with the smallest product => label 0
    #    The cluster with the largest product => label 2
    #    The leftover => label 1
    sorted_clusters = sorted(measure.items(), key=lambda x: x[1])  # ascending
    # e.g. [ (cluster_id=1, measure=1000), (cluster_id=2, measure=50000), (cluster_id=0, measure=300000) ]

    new_label_map = {}
    for i, (cluster_id, _) in enumerate(sorted_clusters):
        # i=0 => cluster 0, i=1 => cluster 1, i=2 => cluster 2
        new_label_map[cluster_id] = i

    return new_label_map


fm_label_map = reorder_clusters_fm(
    df=rfm_df,
    cluster_col="KMeans_FM_Cluster",
    freq_col="Frequency",  # raw Frequency
    mon_col="Monetary",  # raw Monetary
)
rfm_df["KMeans_FM_Cluster"] = rfm_df["KMeans_FM_Cluster"].map(fm_label_map)

# ✅ Store Clusters in DataFrame
df["RFM_Cluster"] = kmeans_rfm.labels_
df["RF_Cluster"] = kmeans_rf.labels_
df["RM_Cluster"] = kmeans_rm.labels_
df["FM_Cluster"] = kmeans_fm.labels_
df["LRFMP_Cluster"] = kmeans_lrfmp.labels_

# ✅ Apply DBSCAN for Outlier Detection
dbscan_rfm = DBSCAN(eps=1.2, min_samples=5).fit(X_rfm)
dbscan_lrfmp = DBSCAN(eps=1.5, min_samples=3).fit(X_lrfmp)

df["DBSCAN_RFM"] = dbscan_rfm.labels_
df["DBSCAN_LRFMP"] = dbscan_lrfmp.labels_

# ✅ Convert Outliers (-1) to "Outlier"
df["DBSCAN_RFM"] = df["DBSCAN_RFM"].apply(lambda x: "Outlier" if x == -1 else str(x))
df["DBSCAN_LRFMP"] = df["DBSCAN_LRFMP"].apply(
    lambda x: "Outlier" if x == -1 else str(x)
)

# ✅ Insert Data into MySQL
cursor.execute("DELETE FROM customer_rfm_analysis")
cursor.execute("DELETE FROM customer_lrfmp_analysis")

for _, row in df.iterrows():
    cursor.execute(
        """
        INSERT INTO customer_rfm_analysis 
        (CustomerID, Recency, Frequency, Monetary, R, F, M, RFMGroup, RFMScore, RFM_Cluster_RFM, RFM_DBSCAN_Segment)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """,
        tuple(
            row[
                [
                    "customer_id",
                    "recency",
                    "frequency",
                    "monetary",
                    "R",
                    "F",
                    "M",
                    "RFMGroup",
                    "RFMScore",
                    "RFM_Cluster_RFM",
                    "RFM_DBSCAN_Segment",
                ]
            ]
        ),
    )
for _, row in df.iterrows():
    cursor.execute(
        """
        INSERT INTO customer_lrfmp_analysis 
        (CustomerID, Length, Recency, Frequency, Monetary, Periodicity, 
         L, R, F, M, P, LRFMPGroup, LRFMPScore, LRFMP_Cluster_LRFMP, LRFMP_DBSCAN_Segment)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """,
        (
            int(row["customer_id"]),
            int(row["length"]),
            int(row["recency"]),
            int(row["frequency"]),
            float(row["monetary"]),
            float(row["periodicity"]),
            int(row["L"]),
            int(row["R"]),
            int(row["F"]),
            int(row["M"]),
            int(row["P"]),
            str(row["LRFMPGroup"]),
            int(row["LRFMPScore"]),
            int(row["LRFMP_Cluster_LRFMP"]),  # ✅ Fixed Cluster Name
            str(row["LRFMP_DBSCAN_Segment"]),  # ✅ Fixed DBSCAN Segment
        ),
    )

conn.commit()
cursor.close()
conn.close()

print("✅ Fixed: RFM & LRFMP Data Successfully Inserted! 🚀")
