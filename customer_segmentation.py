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
    SELECT customer_id, country, MIN(invoice_date) AS first_purchase, 
           MAX(invoice_date) AS last_purchase, COUNT(DISTINCT invoice_no) AS frequency, 
           SUM(total_price) AS monetary 
    FROM cleaned_transactions GROUP BY customer_id, country
""")
data = cursor.fetchall()

columns = ["customer_id", "country", "first_purchase", "last_purchase", "frequency", "monetary"]
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

# ✅ Compute RFM & LRFMP Quantiles for Scoring
rfm_quantiles = df[["recency", "frequency", "monetary"]].quantile(q=[0.25, 0.5, 0.75]).to_dict()
lrfmp_quantiles = df[["length", "recency", "frequency", "monetary", "periodicity"]].quantile(q=[0.25, 0.5, 0.75]).to_dict()

# ✅ Scoring Functions
def Score(x, p, d, reverse=False):
    if reverse:
        if x <= d[p][0.25]: return 4
        elif x <= d[p][0.5]: return 3
        elif x <= d[p][0.75]: return 2
        else: return 1
    else:
        if x <= d[p][0.25]: return 1
        elif x <= d[p][0.5]: return 2
        elif x <= d[p][0.75]: return 3
        else: return 4

# ✅ Assign Scores for RFM & LRFMP
df["R"] = df["recency"].apply(Score, args=("recency", rfm_quantiles, True))
df["F"] = df["frequency"].apply(Score, args=("frequency", rfm_quantiles, False))
df["M"] = df["monetary"].apply(Score, args=("monetary", rfm_quantiles, False))

df["L"] = df["length"].apply(Score, args=("length", lrfmp_quantiles, False))
df["P"] = df["periodicity"].apply(Score, args=("periodicity", lrfmp_quantiles, False))

df["RFMGroup"] = df.apply(lambda row: f"{row['R']}{row['F']}{row['M']}", axis=1)
df["RFMScore"] = df[["R", "F", "M"]].sum(axis=1)

df["LRFMPGroup"] = df.apply(lambda row: f"{row['L']}{row['R']}{row['F']}{row['M']}{row['P']}", axis=1)
df["LRFMPScore"] = df[["L", "R", "F", "M", "P"]].sum(axis=1)

# ✅ Optimize DBSCAN eps
eps_values = np.arange(0.2, 5.0, 0.2)
min_samples_values = [3, 5, 7, 10]

results = {}
scaler = StandardScaler()
X_rfm = scaler.fit_transform(df[["recency", "frequency", "monetary"]])

for min_samples in min_samples_values:
    cluster_counts = []
    for eps in eps_values:
        dbscan = DBSCAN(eps=eps, min_samples=min_samples)
        clusters = dbscan.fit_predict(X_rfm)
        n_clusters = len(set(clusters)) - (1 if -1 in clusters else 0)
        cluster_counts.append(n_clusters)
    results[min_samples] = cluster_counts

# ✅ Plot eps optimization
plt.figure(figsize=(10, 6))
for min_samples, clusters in results.items():
    plt.plot(eps_values, clusters, marker='o', linestyle='-', label=f'min_samples={min_samples}')
plt.xlabel("Epsilon (eps)")
plt.ylabel("Number of Clusters")
plt.title("Optimizing `eps` for DBSCAN")
plt.legend()
plt.grid()
plt.show()

# ✅ Apply DBSCAN with Optimized Parameters
best_eps = 1.2  
best_min_samples = 5  

dbscan_rfm = DBSCAN(eps=best_eps, min_samples=best_min_samples)
dbscan_lrfmp = DBSCAN(eps=best_eps + 0.3, min_samples=best_min_samples)  

df["RFMCluster"] = dbscan_rfm.fit_predict(X_rfm)

X_lrfmp = scaler.fit_transform(df[["length", "recency", "frequency", "monetary", "periodicity"]])
df["LRFMPCluster"] = dbscan_lrfmp.fit_predict(X_lrfmp)

# ✅ Store Data in MySQL
cursor.execute("DELETE FROM customer_rfm_analysis")
cursor.execute("DELETE FROM customer_lrfmp_analysis")

for _, row in df.iterrows():
    cursor.execute("""
        REPLACE INTO customer_rfm_analysis 
        (CustomerID, Recency, Frequency, Monetary, R, F, M, RFMGroup, RFMScore, Cluster)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """, (row.customer_id, row.recency, row.frequency, row.monetary, row.R, row.F, row.M, row.RFMGroup, row.RFMScore, row.RFMCluster))

    cursor.execute("""
        REPLACE INTO customer_lrfmp_analysis 
        (CustomerID, Length, Recency, Frequency, Monetary, Periodicity, 
         L, R, F, M, P, LRFMPGroup, LRFMPScore, Cluster)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """, (row.customer_id, row.length, row.recency, row.frequency, row.monetary, row.periodicity,
          row.L, row.R, row.F, row.M, row.P, row.LRFMPGroup, row.LRFMPScore, row.LRFMPCluster))

conn.commit()
cursor.close()
conn.close()
print("✅ RFM & LRFMP Analysis Completed & Saved to MySQL")
