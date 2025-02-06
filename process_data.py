import sys
import pandas as pd
import numpy as np
import mysql.connector
from datetime import datetime
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import DBSCAN

# ✅ Step 1: Check if CSV File is Provided
if len(sys.argv) < 2:
    print("Error: No CSV file provided.")
    sys.exit(1)

file_path = sys.argv[1]

# ✅ Step 2: Load the CSV File
try:
    df = pd.read_csv(file_path, encoding='ISO-8859-1')
    print("✅ CSV file loaded successfully!")
except Exception as e:
    print(f"Error reading file: {e}")
    sys.exit(1)

# ✅ Step 3: Data Cleaning
df.drop_duplicates(inplace=True)
df.dropna(inplace=True)

# Remove canceled transactions (InvoiceNo starting with 'C')
df = df[~df['InvoiceNo'].astype(str).str.startswith('C')]

# Convert InvoiceDate to datetime
df['InvoiceDate'] = pd.to_datetime(df['InvoiceDate'], errors='coerce')
df.dropna(subset=['InvoiceDate'], inplace=True)

# Remove zero or negative values in Quantity & UnitPrice
df = df[df['Quantity'] > 0]
df = df[df['UnitPrice'] > 0]

# Compute total amount spent
df['TotalAmount'] = df['Quantity'] * df['UnitPrice']

print("✅ Data cleaned successfully!")

# ✅ Step 4: Compute RFM & LRFMP Metrics
latest_date = df['InvoiceDate'].max()

rfm = df.groupby('CustomerID').agg({
    'InvoiceDate': lambda x: (latest_date - x.max()).days,  # Recency
    'InvoiceNo': 'nunique',  # Frequency
    'TotalAmount': 'sum'  # Monetary
}).reset_index()

rfm.columns = ['CustomerID', 'Recency', 'Frequency', 'Monetary']

# Compute LRFMP
lrfmp = df.groupby('CustomerID').agg({
    'InvoiceDate': [
        lambda x: (latest_date - x.max()).days,  # Recency
        lambda x: (x.max() - x.min()).days  # Length
    ],
    'InvoiceNo': 'nunique',  # Frequency
    'TotalAmount': 'sum'  # Monetary
}).reset_index()

# Rename columns
lrfmp.columns = ['CustomerID', 'Recency', 'Length', 'Frequency', 'Monetary']

# Compute Periodicity (Frequency divided by Length)
lrfmp['Periodicity'] = lrfmp['Frequency'] / (lrfmp['Length'] + 1)  # Avoid division by zero

# Ensure no invalid values
lrfmp['Length'] = lrfmp['Length'].apply(lambda x: max(x, 1))
lrfmp['Periodicity'] = lrfmp['Periodicity'].apply(lambda x: max(x, 0.01))

print("✅ RFM & LRFMP metrics calculated!")

# ✅ Step 5: Normalize Data for DBSCAN
scaler = StandardScaler()
rfm_scaled = scaler.fit_transform(rfm[['Recency', 'Frequency', 'Monetary']])
lrfmp_scaled = scaler.fit_transform(lrfmp[['Recency', 'Length', 'Frequency', 'Monetary', 'Periodicity']])

# ✅ Step 6: Apply DBSCAN Clustering
dbscan_rfm = DBSCAN(eps=1.5, min_samples=5)
rfm['Cluster'] = dbscan_rfm.fit_predict(rfm_scaled)

dbscan_lrfmp = DBSCAN(eps=1.5, min_samples=5)
lrfmp['Cluster'] = dbscan_lrfmp.fit_predict(lrfmp_scaled)

print("✅ DBSCAN Clustering applied!")

# ✅ Step 7: Save to MySQL
db_config = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "retail_db"
}

conn = mysql.connector.connect(**db_config)
cursor = conn.cursor()

# Create tables if they don't exist
cursor.execute("""
    CREATE TABLE IF NOT EXISTS customer_segments_rfm (
        customer_id INT PRIMARY KEY,
        recency INT,
        frequency INT,
        monetary FLOAT,
        cluster INT
    )
""")

cursor.execute("""
    CREATE TABLE IF NOT EXISTS customer_segments_lrfmp (
        customer_id INT PRIMARY KEY,
        recency INT,
        length INT,
        frequency INT,
        monetary FLOAT,
        periodicity FLOAT,
        cluster INT
    )
""")

# Clear old data
cursor.execute("DELETE FROM customer_segments_rfm")
cursor.execute("DELETE FROM customer_segments_lrfmp")

# Insert RFM clusters
for _, row in rfm.iterrows():
    cursor.execute("""
        INSERT INTO customer_segments_rfm (customer_id, recency, frequency, monetary, cluster) 
        VALUES (%s, %s, %s, %s, %s)
    """, (row['CustomerID'], row['Recency'], row['Frequency'], row['Monetary'], row['Cluster']))

# Insert LRFMP clusters
for _, row in lrfmp.iterrows():
    cursor.execute("""
        INSERT INTO customer_segments_lrfmp (customer_id, recency, length, frequency, monetary, periodicity, cluster) 
        VALUES (%s, %s, %s, %s, %s, %s, %s)
    """, (row['CustomerID'], row['Recency'], row['Length'], row['Frequency'], row['Monetary'], row['Periodicity'], row['Cluster']))

conn.commit()
cursor.close()
conn.close()

print("✅ Data successfully saved to MySQL!")
