import sys
import pandas as pd
import numpy as np
import mysql.connector
from datetime import datetime
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import DBSCAN


if len(sys.argv) < 2:
    print("Error: No CSV file provided.")
    sys.exit(1)

file_path = sys.argv[1]

try:
    df = pd.read_csv(file_path, encoding='ISO-8859-1')
    print("✅ CSV file loaded successfully!")
except Exception as e:
    print(f"Error reading file: {e}")
    sys.exit(1)

df.drop_duplicates(inplace=True)
df.dropna(inplace=True)


df = df[~df['InvoiceNo'].astype(str).str.startswith('C')]


df['InvoiceDate'] = pd.to_datetime(df['InvoiceDate'], errors='coerce')
df.dropna(subset=['InvoiceDate'], inplace=True)


df = df[df['Quantity'] > 0]
df = df[df['UnitPrice'] > 0]


df['TotalAmount'] = df['Quantity'] * df['UnitPrice']

print("✅ Data cleaned successfully!")


latest_date = df['InvoiceDate'].max()

rfm = df.groupby('CustomerID').agg({
    'InvoiceDate': lambda x: (latest_date - x.max()).days,  
    'InvoiceNo': 'nunique',  # Frequency
    'TotalAmount': 'sum'  # Monetary
}).reset_index()

rfm.columns = ['CustomerID', 'Recency', 'Frequency', 'Monetary']

# Normalize data
scaler = StandardScaler()
rfm_scaled = scaler.fit_transform(rfm[['Recency', 'Frequency', 'Monetary']])

# 🔹 Step 4: Apply DBSCAN Clustering
dbscan = DBSCAN(eps=1.5, min_samples=5)
rfm['Cluster'] = dbscan.fit_predict(rfm_scaled)

print("✅ DBSCAN Clustering applied!")

# 🔹 Step 5: Save to MySQL
db_config = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "retail_db"
}

conn = mysql.connector.connect(**db_config)
cursor = conn.cursor()

cursor.execute("DELETE FROM customer_segments") 
for _, row in rfm.iterrows():
    cursor.execute("""
        INSERT INTO customer_segments (customer_id, recency, frequency, monetary, cluster) 
        VALUES (%s, %s, %s, %s, %s)
    """, (row['CustomerID'], row['Recency'], row['Frequency'], row['Monetary'], row['Cluster']))

conn.commit()
cursor.close()
conn.close()

print("✅ Data successfully saved to MySQL!")
