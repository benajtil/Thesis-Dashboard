import pandas as pd
import numpy as np
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import DBSCAN
import json
from datetime import datetime

# Load transaction data
df = pd.read_csv("../data/transactions.csv", parse_dates=["InvoiceDate"])

# Ensure proper date format
df['InvoiceDate'] = pd.to_datetime(df['InvoiceDate'])
latest_date = df['InvoiceDate'].max()

# Group data and calculate LRFMP metrics
lrfmp_df = df.groupby('CustomerID').agg({
    'InvoiceDate': [
        lambda x: (latest_date - x.max()).days,  # Recency
        lambda x: (x.max() - x.min()).days  # Length
    ],
    'InvoiceNo': 'nunique',  # Frequency
    'total_amt': 'sum'  # Monetary
}).reset_index()

# Flatten MultiIndex columns
lrfmp_df.columns = ['CustomerID', 'Recency', 'Length', 'Frequency', 'Monetary']

# Add Periodicity (Frequency per unit time)
lrfmp_df['Periodicity'] = lrfmp_df['Frequency'] / (lrfmp_df['Length'] + 1)  # Avoid division by zero

# Ensure valid values
lrfmp_df['Length'] = lrfmp_df['Length'].apply(lambda x: max(x, 1))
lrfmp_df['Periodicity'] = lrfmp_df['Periodicity'].apply(lambda x: max(x, 0.01))

# Standardize data for DBSCAN
scaler = StandardScaler()
scaled_features = scaler.fit_transform(lrfmp_df[['Recency', 'Length', 'Frequency', 'Monetary', 'Periodicity']])

# Apply DBSCAN Clustering
dbscan = DBSCAN(eps=1.5, min_samples=5)
clusters = dbscan.fit_predict(scaled_features)

# Add Cluster Labels
lrfmp_df['Cluster'] = clusters

# Save Results to JSON
lrfmp_df.to_json("../data/dbscan_results.json", orient="records")
print("✅ DBSCAN Clustering Completed & Saved!")
