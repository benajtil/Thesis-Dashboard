import pandas as pd
import numpy as np
from sklearn.cluster import DBSCAN
from sklearn.preprocessing import StandardScaler

# Load transaction data
df = pd.read_csv("cleaned_transactions.csv")

# Convert invoice_date to datetime
df["invoice_date"] = pd.to_datetime(df["invoice_date"])

# Aggregate data by Country
country_data = df.groupby("country").agg(
    total_spent=("total_price", "sum"),
    total_orders=("invoice_no", "nunique"),
    recency=("invoice_date", lambda x: (df["invoice_date"].max() - x.max()).days),
    frequency=("invoice_no", "count"),
    avg_order_value=("total_price", "mean"),
).reset_index()

# Normalize features
scaler = StandardScaler()
features = scaler.fit_transform(country_data[['total_spent', 'total_orders', 'recency', 'frequency', 'avg_order_value']])

# Apply DBSCAN Clustering
dbscan = DBSCAN(eps=0.5, min_samples=3)
country_data["cluster"] = dbscan.fit_predict(features)

# Save results
country_data.to_csv("dbscan_output.csv", index=False)
print("DBSCAN clustering applied successfully!")
