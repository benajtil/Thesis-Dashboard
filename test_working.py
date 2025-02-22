import pandas as pd
import numpy as np
import mysql.connector
from datetime import datetime
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import DBSCAN
from sklearn.metrics import silhouette_score, davies_bouldin_score

# ✅ Connect to MySQL Database
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="retail_db"
)
cursor = conn.cursor()

# ✅ Ensure required tables exist
cursor.execute("""
    CREATE TABLE IF NOT EXISTS customer_rfm (
        customer_id INT PRIMARY KEY,
        recency INT NOT NULL,
        frequency INT NOT NULL,
        monetary DECIMAL(10,2) NOT NULL
    )
""")

cursor.execute("""
    CREATE TABLE IF NOT EXISTS customer_lrfmp (
        customer_id INT PRIMARY KEY,
        length INT NOT NULL,
        recency INT NOT NULL,
        frequency INT NOT NULL,
        monetary DECIMAL(10,2) NOT NULL,
        periodicity DECIMAL(10,2) NOT NULL
    )
""")

cursor.execute("""
    CREATE TABLE IF NOT EXISTS customer_segments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        country VARCHAR(100) NOT NULL,
        total_spent DECIMAL(10,2) NOT NULL,
        total_orders INT NOT NULL,
        recency INT NOT NULL,
        frequency INT NOT NULL,
        monetary DECIMAL(10,2) NOT NULL,
        length INT NOT NULL,
        periodicity DECIMAL(10,2) NOT NULL,
        dbscan_cluster INT NOT NULL,
        segment VARCHAR(50) NOT NULL
    )
""")

conn.commit()
print("✅ Required tables checked/created successfully!")

# ✅ Fetch cleaned transactions
query = """
    SELECT customer_id, country, MIN(invoice_date) AS first_purchase, 
           MAX(invoice_date) AS last_purchase, COUNT(DISTINCT invoice_no) AS frequency, 
           SUM(total_price) AS monetary 
    FROM cleaned_transactions GROUP BY customer_id, country
"""
df = pd.read_sql(query, conn)

# ✅ Compute Recency, Length, Periodicity
df["first_purchase"] = pd.to_datetime(df["first_purchase"])
df["last_purchase"] = pd.to_datetime(df["last_purchase"])
df["recency"] = (datetime.today() - df["last_purchase"]).dt.days
df["length"] = (df["last_purchase"] - df["first_purchase"]).dt.days
df["periodicity"] = df["length"] / df["frequency"]

# ✅ Remove anomalies
df.dropna(inplace=True)
df = df[df["monetary"] > 0]
df = df[df["periodicity"] > 0]

# ✅ Store RFM & LRFMP into MySQL
cursor.execute("DELETE FROM customer_rfm")  # Clear Old Data
cursor.execute("DELETE FROM customer_lrfmp")  # Clear Old Data

for _, row in df.iterrows():
    cursor.execute("""
        REPLACE INTO customer_rfm (customer_id, recency, frequency, monetary)
        VALUES (%s, %s, %s, %s)
    """, (row.customer_id, row.recency, row.frequency, row.monetary))

    cursor.execute("""
        REPLACE INTO customer_lrfmp (customer_id, length, recency, frequency, monetary, periodicity)
        VALUES (%s, %s, %s, %s, %s, %s)
    """, (row.customer_id, row.length, row.recency, row.frequency, row.monetary, row.periodicity))

conn.commit()
print("✅ RFM & LRFMP Data Saved!")


# ✅ Apply DBSCAN Clustering
features = ["recency", "frequency", "monetary", "length", "periodicity"]
scaler = StandardScaler()
scaled_data = scaler.fit_transform(df[features])
dbscan = DBSCAN(eps=1.5, min_samples=5)
df["dbscan_cluster"] = dbscan.fit_predict(scaled_data)

# ✅ Store Clusters in MySQL
cursor.execute("DELETE FROM customer_segments")
for _, row in df.iterrows():
    cursor.execute("""
        INSERT INTO customer_segments (customer_id, country, total_spent, total_orders, recency, frequency, monetary, length, periodicity, dbscan_cluster, segment)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'Segment')
    """, (row.customer_id, row.country, row.monetary, row.frequency, row.recency, row.frequency, row.monetary, row.length, row.periodicity, row.dbscan_cluster))

conn.commit()

# ✅ Compute Cluster Evaluation Metrics
silhouette = silhouette_score(scaled_data, df["dbscan_cluster"]) if len(set(df["dbscan_cluster"])) > 1 else None
davies_bouldin = davies_bouldin_score(scaled_data, df["dbscan_cluster"]) if len(set(df["dbscan_cluster"])) > 1 else None

print(f"✅ Silhouette Score: {silhouette}")
print(f"✅ Davies-Bouldin Index: {davies_bouldin}")

cursor.close()
conn.close()
print("✅ Customer Segmentation Completed & Saved to MySQL")
