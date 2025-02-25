# Re-import necessary libraries due to execution state reset
import pandas as pd
import time
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import KMeans, AgglomerativeClustering, DBSCAN
from sklearn.metrics import silhouette_score, davies_bouldin_score

# Reload the dataset
file_path = "/mnt/data/Onlin1eRetail.csv"
df = pd.read_csv(file_path, encoding="ISO-8859-1")

# Convert InvoiceDate to datetime format
df["InvoiceDate"] = pd.to_datetime(df["InvoiceDate"], errors='coerce')

# Drop rows with missing CustomerID
df = df.dropna(subset=["CustomerID"])
df["CustomerID"] = df["CustomerID"].astype(int)

# Compute Total Monetary Value
df["TotalPrice"] = df["Quantity"] * df["UnitPrice"]

# Compute Recency
latest_date = df["InvoiceDate"].max()
recency_df = df.groupby("CustomerID")["InvoiceDate"].max().reset_index()
recency_df["Recency"] = (latest_date - recency_df["InvoiceDate"]).dt.days

# Compute Frequency
frequency_df = df.groupby("CustomerID")["InvoiceNo"].nunique().reset_index()
frequency_df.columns = ["CustomerID", "Frequency"]

# Compute Monetary
monetary_df = df.groupby("CustomerID")["TotalPrice"].sum().reset_index()
monetary_df.columns = ["CustomerID", "Monetary"]

# Compute Length (First Purchase)
length_df = df.groupby("CustomerID")["InvoiceDate"].min().reset_index()
length_df["Length"] = (latest_date - length_df["InvoiceDate"]).dt.days

# Compute Periodicity
periodicity_df = df.groupby("CustomerID")["InvoiceDate"].apply(lambda x: x.diff().mean()).reset_index()
periodicity_df["InvoiceDate"] = periodicity_df["InvoiceDate"].dt.days.fillna(0)
periodicity_df.columns = ["CustomerID", "Periodicity"]

# Merge all LRFMP features
lrfmp_df = recency_df.merge(frequency_df, on="CustomerID")\
                     .merge(monetary_df, on="CustomerID")\
                     .merge(length_df, on="CustomerID")\
                     .merge(periodicity_df, on="CustomerID")

# Select relevant columns
lrfmp_df = lrfmp_df[["CustomerID", "Length", "Recency", "Frequency", "Monetary", "Periodicity"]]

# Prepare data for clustering
features = ["Length", "Recency", "Frequency", "Monetary", "Periodicity"]
X = lrfmp_df[features]

# Standardize the data
scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)

# Dictionary to store clustering results
clustering_results = {}

# --- Apply DBSCAN ---
start_time = time.time()
dbscan = DBSCAN(eps=0.5, min_samples=5)
dbscan_labels = dbscan.fit_predict(X_scaled)
dbscan_time = time.time() - start_time

# --- Apply K-Means (Optimal K = 3 for comparison) ---
start_time = time.time()
kmeans = KMeans(n_clusters=3, random_state=42, n_init=10)
kmeans_labels = kmeans.fit_predict(X_scaled)
kmeans_time = time.time() - start_time

# --- Apply Hierarchical Clustering (Agglomerative) ---
start_time = time.time()
hierarchical = AgglomerativeClustering(n_clusters=3)
hierarchical_labels = hierarchical.fit_predict(X_scaled)
hierarchical_time = time.time() - start_time

# Add cluster labels to the dataset
lrfmp_df["DBSCAN_Cluster"] = dbscan_labels
lrfmp_df["KMeans_Cluster"] = kmeans_labels
lrfmp_df["Hierarchical_Cluster"] = hierarchical_labels

# Compute average values of segmentation criteria per cluster
dbscan_results = lrfmp_df.groupby("DBSCAN_Cluster")[["Recency", "Frequency", "Monetary", "Length", "Periodicity"]].mean()
kmeans_results = lrfmp_df.groupby("KMeans_Cluster")[["Recency", "Frequency", "Monetary", "Length", "Periodicity"]].mean()
hierarchical_results = lrfmp_df.groupby("Hierarchical_Cluster")[["Recency", "Frequency", "Monetary", "Length", "Periodicity"]].mean()

# Create a summary table comparing clustering results
lrfmp_comparison_table = pd.concat([
    dbscan_results.mean().rename("DBSCAN"),
    kmeans_results.mean().rename("K-Means"),
    hierarchical_results.mean().rename("Hierarchical")
], axis=1).round(2)

# Display the final LRFMP comparison table
import ace_tools as tools
tools.display_dataframe_to_user(name="LRFMP and RFM Clustering Comparison Table", dataframe=lrfmp_comparison_table)
