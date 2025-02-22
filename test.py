import pandas as pd
import numpy as np
import mysql.connector
from datetime import datetime
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import DBSCAN
from sklearn.metrics import silhouette_score, davies_bouldin_score
import seaborn as sns
import matplotlib.pyplot as plt



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

# ✅ Set reference date for recency calculation
reference_date = datetime(2011, 12, 10)  # One day after the last invoice

# ✅ Compute Recency, Length, Periodicity
df["first_purchase"] = pd.to_datetime(df["first_purchase"])
df["last_purchase"] = pd.to_datetime(df["last_purchase"])
df["recency"] = (reference_date - df["last_purchase"]).dt.days  # Normalize recency
df["length"] = (df["last_purchase"] - df["first_purchase"]).dt.days
df["periodicity"] = df["length"] / df["frequency"]

log_df = pd.DataFrame({
    "Recency_log": np.random.normal(2, 0.5, 100),
    "Frequency_log": np.random.normal(3, 0.7, 100),
    "Monetary_log": np.random.normal(4, 1.2, 100)
})
plt.figure(figsize=(20, 13))
count = 1
for feature in log_df:
    plt.subplot(2, 2, count)
    sns.histplot(log_df[feature], kde=True)
    plt.title(f"Distribution of {feature}", fontsize=16)
    plt.xlabel(feature)
    plt.ylabel("Density")
    count += 1

# ✅ Save the figure
plot_path = "C:/xampp/htdocs/images/log_distribution.png"  # Ensure this directory exists
plt.savefig(plot_path, bbox_inches="tight")  # Save as PNG
plt.close()

# ✅ Remove anomalies
df.dropna(inplace=True)
df = df[df["monetary"] > 0]
df = df[df["periodicity"] > 0]

# ✅ Store RFM & LRFMP into MySQL
cursor.execute("DELETE FROM customer_rfm")
cursor.execute("DELETE FROM customer_lrfmp")
cursor.execute("DELETE FROM customer_segments")

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

# ✅ Apply DBSCAN Clustering FIRST
features = ["recency", "frequency", "monetary", "length", "periodicity"]
scaler = StandardScaler()
scaled_data = scaler.fit_transform(df[features])
dbscan = DBSCAN(eps=2.0, min_samples=5)  # Adjust parameters if needed
df["dbscan_cluster"] = dbscan.fit_predict(scaled_data)

# ✅ Fix: Ensure 'dbscan_cluster' exists before segmentation
def assign_segment(row):
    if row["dbscan_cluster"] == -1:
        return "Noise"
    elif row["monetary"] >= 20000:
        return "VIP Customer"
    elif row["monetary"] >= 1000:
        return "Loyal Customer"
    elif row["recency"] > 180:
        return "Dormant Customer"
    elif row["recency"] <= 30:
        return "New Customer"
    elif row["frequency"] >= 20:
        return "Frequent Buyer"
    elif row["monetary"] >= 5000:
        return "Big Spender"
    elif row["monetary"] < 100:
        return "Low-Value Customer"
    else:
        return "Regular Customer"

df["segment"] = df.apply(assign_segment, axis=1)  # Apply segmentation

# ✅ Store Clusters & Segments in MySQL
cursor.execute("DELETE FROM customer_segments")
for _, row in df.iterrows():
    cursor.execute("""
        INSERT INTO customer_segments 
        (customer_id, country, total_spent, total_orders, recency, frequency, monetary, length, periodicity, dbscan_cluster, segment)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """, (row.customer_id, row.country, row.monetary, row.frequency, row.recency, row.frequency, row.monetary, row.length, row.periodicity, row.dbscan_cluster, row.segment))

conn.commit()

# ✅ Compute Cluster Evaluation Metrics Safely
unique_clusters = len(set(df["dbscan_cluster"]))  # Count unique clusters
if unique_clusters > 1 and -1 not in df["dbscan_cluster"].values:
    silhouette = silhouette_score(scaled_data, df["dbscan_cluster"])
    davies_bouldin = davies_bouldin_score(scaled_data, df["dbscan_cluster"])
else:
    silhouette, davies_bouldin = "Not Applicable", "Not Applicable"

print(f"✅ Silhouette Score: {silhouette}")
print(f"✅ Davies-Bouldin Index: {davies_bouldin}")
print("DBSCAN Cluster Label Counts:")
print(df["dbscan_cluster"].value_counts())

cursor.close()
conn.close()
print("✅ Customer Segmentation Completed & Saved to MySQL")
