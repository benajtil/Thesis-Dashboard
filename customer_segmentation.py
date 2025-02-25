import pandas as pd
import numpy as np
import datetime as dt
import mysql.connector
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import KMeans, DBSCAN

conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",  
    database="retail_db",
)
cursor = conn.cursor()

SQL = """
SELECT 
    invoice_no,
    invoice_date,
    customer_id,
    quantity,
    unit_price,
    total_price,
    country
FROM cleaned_transactions
WHERE customer_id IS NOT NULL
"""
df = pd.read_sql(SQL, conn)
print("Initial shape:", df.shape)
print(df.head(3))

cursor.close()
conn.close()

df.rename(
    columns={
        "invoice_no": "InvoiceNo",
        "invoice_date": "InvoiceDate",
        "customer_id": "CustomerID",
        "quantity": "Quantity",
        "unit_price": "UnitPrice",
        "total_price": "total_amt",
    },
    inplace=True,
)

df["InvoiceDate"] = pd.to_datetime(df["InvoiceDate"], errors="coerce")
df.drop_duplicates(inplace=True)
df.dropna(subset=["CustomerID", "InvoiceDate"], inplace=True)


df = df[(df["Quantity"] > 0) & (df["UnitPrice"] > 0)]
print("After cleaning:", df.shape)
print(df.head(3))


latest_date = df["InvoiceDate"].max() + pd.Timedelta(days=1)


rfm_df = (
    df.groupby("CustomerID")
    .agg(
        {
            "InvoiceDate": lambda x: (latest_date - x.max()).days,  
            "InvoiceNo": "nunique", 
            "total_amt": "sum",    
        }
    )
    .reset_index()
)
rfm_df.rename(
    columns={
        "InvoiceDate": "Recency",
        "InvoiceNo": "Frequency",
        "total_amt": "Monetary",
    },
    inplace=True,
)


rfm_country = df.groupby("CustomerID")["country"].first().reset_index()
rfm_df = pd.merge(rfm_df, rfm_country, on="CustomerID", how="left")
rfm_df.rename(columns={"country": "Country"}, inplace=True)

print("RFM shape:", rfm_df.shape)
print(rfm_df.head(3))

lrfmp_temp = df.groupby("CustomerID").agg(
    {
        "InvoiceDate": [lambda x: x.min(), lambda x: x.max()],
        "InvoiceNo": "nunique",
        "total_amt": "sum",
    }
)
lrfmp_temp.columns = ["first_purchase", "last_purchase", "Frequency", "Monetary"]
lrfmp_temp.reset_index(inplace=True)

lrfmp_temp["Recency"] = (latest_date - lrfmp_temp["last_purchase"]).dt.days
lrfmp_temp["Length"] = (
    lrfmp_temp["last_purchase"] - lrfmp_temp["first_purchase"]
).dt.days


lrfmp_temp["Periodicity"] = lrfmp_temp.apply(
    lambda row: row["Length"] / row["Frequency"] if row["Frequency"] > 0 else 0, axis=1
)

lrfmp_temp.drop(["first_purchase", "last_purchase"], axis=1, inplace=True)


lrfmp_country = df.groupby("CustomerID")["country"].first().reset_index()
lrfmp_temp = pd.merge(lrfmp_temp, lrfmp_country, on="CustomerID", how="left")
lrfmp_temp.rename(columns={"country": "Country"}, inplace=True)


def handle_nonpositive(x):
    """If x <= 0, set to 1 to avoid log(0) or negative values."""
    return x if x > 0 else 1


for col in ["Recency", "Frequency", "Monetary"]:
    rfm_df[col] = rfm_df[col].apply(handle_nonpositive)

rfm_df["Recency_log"] = np.log(rfm_df["Recency"])
rfm_df["Frequency_log"] = np.log(rfm_df["Frequency"])
rfm_df["Monetary_log"] = np.log(rfm_df["Monetary"])


lrfmp_df = lrfmp_temp.copy()
for col in ["Length", "Recency", "Frequency", "Monetary", "Periodicity"]:
    lrfmp_df[col] = lrfmp_df[col].apply(handle_nonpositive)

lrfmp_df["Length_log"] = np.log(lrfmp_df["Length"])
lrfmp_df["Recency_log"] = np.log(lrfmp_df["Recency"])
lrfmp_df["Frequency_log"] = np.log(lrfmp_df["Frequency"])
lrfmp_df["Monetary_log"] = np.log(lrfmp_df["Monetary"])
lrfmp_df["Periodicity_log"] = np.log(lrfmp_df["Periodicity"])


def reorder_clusters_lrfmp(
    df,
    cluster_col="KMeans_LRFMP_Cluster",
    len_col="Length",
    recency_col="Recency",
    freq_col="Frequency",
    mon_col="Monetary",
    per_col="Periodicity"
):
    """
    Reorder cluster labels (k=3) so that:
      - cluster 0 => "Dormant"  => Low Length, High Recency, Low F, Low M, Low P
      - cluster 2 => "Best"     => High Length, Low Recency, High F, High M, High P
      - cluster 1 => in-between

    We define measure = (avg Recency) - (avg Length + avg Frequency + avg Monetary + avg Periodicity).
      => A big positive measure => High Recency, Low everything else => Dormant
      => A small / negative measure => Low Recency, High everything else => Best
    """

    stats = df.groupby(cluster_col).agg({
        len_col: "mean",
        recency_col: "mean",
        freq_col: "mean",
        mon_col: "mean",
        per_col: "mean"
    })


    measure = {}
    for c in stats.index:
        l_mean = stats.loc[c, len_col]
        r_mean = stats.loc[c, recency_col]
        f_mean = stats.loc[c, freq_col]
        m_mean = stats.loc[c, mon_col]
        p_mean = stats.loc[c, per_col]

        measure[c] = r_mean - (l_mean + f_mean + m_mean + p_mean)

    sorted_desc = sorted(measure.items(), key=lambda x: x[1], reverse=True)


    new_label_map = {}
    for i, (original_cluster, _) in enumerate(sorted_desc):
        new_label_map[original_cluster] = i

    return new_label_map



def reorder_clusters_rfm(
    df, cluster_col="KMeans_RFM_Cluster", recency_col="Recency", freq_col="Frequency"
):
    """
    Reorder cluster labels (k=3) so that:
      - cluster 0 => "worst" (highest recency, lowest frequency)
      - cluster 2 => "best"  (lowest recency, highest frequency)
      - cluster 1 => in between
    We'll define measure = mean(Recency) - mean(Frequency).
      The bigger measure => the more "worst"
    """
    stats = df.groupby(cluster_col).agg({recency_col: "mean", freq_col: "mean"})
    measure = {}
    for c in stats.index:
        r_mean = stats.loc[c, recency_col]
        f_mean = stats.loc[c, freq_col]
        measure[c] = r_mean - f_mean  


    sorted_desc = sorted(measure.items(), key=lambda x: x[1], reverse=True)
    new_label_map = {}
    for i, (cluster_id, _) in enumerate(sorted_desc):
        new_label_map[cluster_id] = i
    return new_label_map


def reorder_clusters_fm(
    df, cluster_col="KMeans_FM_Cluster", freq_col="Frequency", mon_col="Monetary"
):
    """
    Re-label the KMeans_FM_Cluster so that:
      - cluster 0 => lowest (Frequency * Monetary)
      - cluster 2 => highest (Frequency * Monetary)
      - cluster 1 => in between
    """
    stats = df.groupby(cluster_col).agg({freq_col: "mean", mon_col: "mean"})
    measure = {}
    for c in stats.index:
        freq_mean = stats.loc[c, freq_col]
        mon_mean = stats.loc[c, mon_col]
        measure[c] = freq_mean * mon_mean

    sorted_asc = sorted(measure.items(), key=lambda x: x[1])  # ascending
    new_label_map = {}
    for i, (cluster_id, _) in enumerate(sorted_asc):
        new_label_map[cluster_id] = i
    return new_label_map


def reorder_clusters_lr(
    df, cluster_col="KMeans_LR_Cluster", length_col="Length", recency_col="Recency"
):
    """
    Re-label L–R so that:
      - cluster 0 => Low Length, High Recency
      - cluster 2 => High Length, Low Recency
      - cluster 1 => in between
    measure = avg(Length) - avg(Recency)
      smaller => 0
      larger  => 2
    """
    stats = df.groupby(cluster_col).agg({length_col: "mean", recency_col: "mean"})
    measure = {}
    for c in stats.index:
        avg_len = stats.loc[c, length_col]
        avg_rec = stats.loc[c, recency_col]
        measure[c] = avg_len - avg_rec

    sorted_asc = sorted(measure.items(), key=lambda x: x[1])  # ascending
    new_label_map = {}
    for i, (cluster_id, _) in enumerate(sorted_asc):
        new_label_map[cluster_id] = i
    return new_label_map


def reorder_clusters_lf(
    df, cluster_col="KMeans_LF_Cluster", length_col="Length", freq_col="Frequency"
):
    """
    Re-label L–F so that:
      - cluster 0 => Low (Length + Frequency)
      - cluster 2 => High (Length + Frequency)
      - cluster 1 => in between
    """
    stats = df.groupby(cluster_col).agg({length_col: "mean", freq_col: "mean"})
    measure = {}
    for c in stats.index:
        measure[c] = stats.loc[c, length_col] + stats.loc[c, freq_col]

    sorted_asc = sorted(measure.items(), key=lambda x: x[1])  # ascending
    new_label_map = {}
    for i, (cluster_id, _) in enumerate(sorted_asc):
        new_label_map[cluster_id] = i
    return new_label_map


def reorder_clusters_lm(
    df, cluster_col="KMeans_LM_Cluster", length_col="Length", mon_col="Monetary"
):
    """
    Re-label L–M so that:
      - cluster 0 => High (Length + Monetary)
      - cluster 2 => Low (Length + Monetary)
      - cluster 1 => in between

    We'll sort by measure descending => cluster with largest sum => 0
    """
    stats = df.groupby(cluster_col).agg({length_col: "mean", mon_col: "mean"})
    measure = {}
    for c in stats.index:
        measure[c] = stats.loc[c, length_col] + stats.loc[c, mon_col]


    sorted_desc = sorted(measure.items(), key=lambda x: x[1], reverse=True)
    new_label_map = {}
    for i, (cluster_id, _) in enumerate(sorted_desc):
        new_label_map[cluster_id] = i
    return new_label_map



scaler_rfm = StandardScaler()


X_full_rfm = scaler_rfm.fit_transform(
    rfm_df[["Recency_log", "Frequency_log", "Monetary_log"]]
)
kmeans_full = KMeans(n_clusters=3, random_state=42, n_init=10)
rfm_df["KMeans_RFM_Cluster"] = kmeans_full.fit_predict(X_full_rfm)


new_label_map_rfm = reorder_clusters_rfm(
    rfm_df,
    cluster_col="KMeans_RFM_Cluster",
    recency_col="Recency",
    freq_col="Frequency",
)
rfm_df["KMeans_RFM_Cluster"] = rfm_df["KMeans_RFM_Cluster"].map(new_label_map_rfm)


dbscan_full = DBSCAN(eps=1.5, min_samples=5)
labels_full = dbscan_full.fit_predict(X_full_rfm)
rfm_df["DBSCAN_RFM_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_full]


X_RF = scaler_rfm.fit_transform(rfm_df[["Recency_log", "Frequency_log"]])
kmeans_RF = KMeans(n_clusters=3, random_state=42, n_init=10)
rfm_df["KMeans_RF_Cluster"] = kmeans_RF.fit_predict(X_RF)


new_label_map_rf = reorder_clusters_rfm(
    rfm_df,
    cluster_col="KMeans_RF_Cluster",
    recency_col="Recency",
    freq_col="Frequency",
)
rfm_df["KMeans_RF_Cluster"] = rfm_df["KMeans_RF_Cluster"].map(new_label_map_rf)

dbscan_RF = DBSCAN(eps=1.2, min_samples=5)
labels_RF = dbscan_RF.fit_predict(X_RF)
rfm_df["DBSCAN_RF_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_RF]


X_RM = scaler_rfm.fit_transform(rfm_df[["Recency_log", "Monetary_log"]])
kmeans_RM = KMeans(n_clusters=3, random_state=42, n_init=10)
rfm_df["KMeans_RM_Cluster"] = kmeans_RM.fit_predict(X_RM)

dbscan_RM = DBSCAN(eps=1.2, min_samples=5)
labels_RM = dbscan_RM.fit_predict(X_RM)
rfm_df["DBSCAN_RM_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_RM]


X_FM = scaler_rfm.fit_transform(rfm_df[["Frequency_log", "Monetary_log"]])
kmeans_FM = KMeans(n_clusters=3, random_state=42, n_init=10)
rfm_df["KMeans_FM_Cluster"] = kmeans_FM.fit_predict(X_FM)


fm_label_map = reorder_clusters_fm(
    rfm_df,
    cluster_col="KMeans_FM_Cluster",
    freq_col="Frequency",
    mon_col="Monetary",
)
rfm_df["KMeans_FM_Cluster"] = rfm_df["KMeans_FM_Cluster"].map(fm_label_map)

dbscan_FM = DBSCAN(eps=1.2, min_samples=5)
labels_FM = dbscan_FM.fit_predict(X_FM)
rfm_df["DBSCAN_FM_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_FM]

scaler_lrfmp = StandardScaler()


X_full_lrfmp = scaler_lrfmp.fit_transform(
    lrfmp_df[["Length_log", "Recency_log", "Frequency_log", "Monetary_log", "Periodicity_log"]]
)
kmeans_full_lrfmp = KMeans(n_clusters=3, random_state=42, n_init=10)
lrfmp_df["KMeans_LRFMP_Cluster"] = kmeans_full_lrfmp.fit_predict(X_full_lrfmp)

dbscan_full_lrfmp = DBSCAN(eps=1.8, min_samples=5)
labels_full_lrfmp = dbscan_full_lrfmp.fit_predict(X_full_lrfmp)
lrfmp_df["DBSCAN_LRFMP_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_full_lrfmp]


X_LR = scaler_lrfmp.fit_transform(lrfmp_df[["Length_log", "Recency_log"]])
kmeans_LR = KMeans(n_clusters=3, random_state=42, n_init=10)
lrfmp_df["KMeans_LR_Cluster"] = kmeans_LR.fit_predict(X_LR)

lr_map = reorder_clusters_lr(
    lrfmp_df, 
    cluster_col="KMeans_LR_Cluster", 
    length_col="Length", 
    recency_col="Recency"
)
lrfmp_df["KMeans_LR_Cluster"] = lrfmp_df["KMeans_LR_Cluster"].map(lr_map)

dbscan_LR = DBSCAN(eps=1.2, min_samples=5)
labels_LR = dbscan_LR.fit_predict(X_LR)
lrfmp_df["DBSCAN_LR_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_LR]


X_LF = scaler_lrfmp.fit_transform(lrfmp_df[["Length_log", "Frequency_log"]])
kmeans_LF = KMeans(n_clusters=3, random_state=42, n_init=10)
lrfmp_df["KMeans_LF_Cluster"] = kmeans_LF.fit_predict(X_LF)

lf_map = reorder_clusters_lf(
    lrfmp_df, 
    cluster_col="KMeans_LF_Cluster", 
    length_col="Length", 
    freq_col="Frequency"
)
lrfmp_df["KMeans_LF_Cluster"] = lrfmp_df["KMeans_LF_Cluster"].map(lf_map)

dbscan_LF = DBSCAN(eps=1.2, min_samples=5)
labels_LF = dbscan_LF.fit_predict(X_LF)
lrfmp_df["DBSCAN_LF_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_LF]


X_LM = scaler_lrfmp.fit_transform(lrfmp_df[["Length_log", "Monetary_log"]])
kmeans_LM = KMeans(n_clusters=3, random_state=42, n_init=10)
lrfmp_df["KMeans_LM_Cluster"] = kmeans_LM.fit_predict(X_LM)

lm_map = reorder_clusters_lm(
    lrfmp_df, 
    cluster_col="KMeans_LM_Cluster", 
    length_col="Length", 
    mon_col="Monetary"
)
lrfmp_df["KMeans_LM_Cluster"] = lrfmp_df["KMeans_LM_Cluster"].map(lm_map)

dbscan_LM = DBSCAN(eps=1.2, min_samples=5)
labels_LM = dbscan_LM.fit_predict(X_LM)
lrfmp_df["DBSCAN_LM_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_LM]


X_FP = scaler_lrfmp.fit_transform(lrfmp_df[["Frequency_log", "Periodicity_log"]])
kmeans_FP = KMeans(n_clusters=3, random_state=42, n_init=10)
lrfmp_df["KMeans_FP_Cluster"] = kmeans_FP.fit_predict(X_FP)

dbscan_FP = DBSCAN(eps=1.2, min_samples=5)
labels_FP = dbscan_FP.fit_predict(X_FP)
lrfmp_df["DBSCAN_FP_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_FP]


X_MP = scaler_lrfmp.fit_transform(lrfmp_df[["Monetary_log", "Periodicity_log"]])
kmeans_MP = KMeans(n_clusters=3, random_state=42, n_init=10)
lrfmp_df["KMeans_MP_Cluster"] = kmeans_MP.fit_predict(X_MP)

dbscan_MP = DBSCAN(eps=1.2, min_samples=5)
labels_MP = dbscan_MP.fit_predict(X_MP)
lrfmp_df["DBSCAN_MP_Flag"] = ["Outlier" if lab == -1 else "Inlier" for lab in labels_MP]



conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",  
    database="retail_db",
)
cursor = conn.cursor()


cursor.execute("DROP TABLE IF EXISTS customer_rfm_kmeans_dbscan")
cursor.execute(
    """
    CREATE TABLE customer_rfm_kmeans_dbscan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT,
        Country VARCHAR(50),
        Recency FLOAT,
        Frequency FLOAT,
        Monetary FLOAT,
        Recency_log FLOAT,
        Frequency_log FLOAT,
        Monetary_log FLOAT,
        KMeans_RFM_Cluster INT,
        DBSCAN_RFM_Flag VARCHAR(20),
        KMeans_RF_Cluster INT,
        DBSCAN_RF_Flag VARCHAR(20),
        KMeans_RM_Cluster INT,
        DBSCAN_RM_Flag VARCHAR(20),
        KMeans_FM_Cluster INT,
        DBSCAN_FM_Flag VARCHAR(20)
    )
"""
)



for _, row in rfm_df.iterrows():
    cursor.execute(
        """
        INSERT INTO customer_rfm_kmeans_dbscan (
            CustomerID,
            Country,
            Recency, Frequency, Monetary,
            Recency_log, Frequency_log, Monetary_log,
            KMeans_RFM_Cluster,
            DBSCAN_RFM_Flag,
            KMeans_RF_Cluster, DBSCAN_RF_Flag,
            KMeans_RM_Cluster, DBSCAN_RM_Flag,
            KMeans_FM_Cluster, DBSCAN_FM_Flag
        )
        VALUES (
            %s, %s, %s, %s, %s,
            %s, %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s
        )
        """,
        (
            int(row["CustomerID"]),
            str(row["Country"]),
            float(row["Recency"]),
            float(row["Frequency"]),
            float(row["Monetary"]),
            float(row["Recency_log"]),
            float(row["Frequency_log"]),
            float(row["Monetary_log"]),
            int(row["KMeans_RFM_Cluster"]),
            str(row["DBSCAN_RFM_Flag"]),
            int(row["KMeans_RF_Cluster"]),
            str(row["DBSCAN_RF_Flag"]),
            int(row["KMeans_RM_Cluster"]),
            str(row["DBSCAN_RM_Flag"]),
            int(row["KMeans_FM_Cluster"]),
            str(row["DBSCAN_FM_Flag"]),
        ),
    )

# Drop & create the LRFMP table
cursor.execute("DROP TABLE IF EXISTS customer_lrfmp_kmeans_dbscan")
cursor.execute(
    """
    CREATE TABLE customer_lrfmp_kmeans_dbscan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CustomerID INT,
        Country VARCHAR(50),
        Length FLOAT,
        Recency FLOAT,
        Frequency FLOAT,
        Monetary FLOAT,
        Periodicity FLOAT,
        Length_log FLOAT,
        Recency_log FLOAT,
        Frequency_log FLOAT,
        Monetary_log FLOAT,
        Periodicity_log FLOAT,
        KMeans_LRFMP_Cluster INT,
        DBSCAN_LRFMP_Flag VARCHAR(20),
        KMeans_LR_Cluster INT,
        DBSCAN_LR_Flag VARCHAR(20),
        KMeans_LF_Cluster INT,
        DBSCAN_LF_Flag VARCHAR(20),
        KMeans_LM_Cluster INT,
        DBSCAN_LM_Flag VARCHAR(20),
        KMeans_FP_Cluster INT,
        DBSCAN_FP_Flag VARCHAR(20),
        KMeans_MP_Cluster INT,
        DBSCAN_MP_Flag VARCHAR(20)
    )
"""
)


for _, row in lrfmp_df.iterrows():
    cursor.execute(
        """
        INSERT INTO customer_lrfmp_kmeans_dbscan (
            CustomerID,
            Country,
            Length, Recency, Frequency, Monetary, Periodicity,
            Length_log, Recency_log, Frequency_log, Monetary_log, Periodicity_log,
            KMeans_LRFMP_Cluster,
            DBSCAN_LRFMP_Flag,
            KMeans_LR_Cluster, DBSCAN_LR_Flag,
            KMeans_LF_Cluster, DBSCAN_LF_Flag,
            KMeans_LM_Cluster, DBSCAN_LM_Flag,
            KMeans_FP_Cluster, DBSCAN_FP_Flag,
            KMeans_MP_Cluster, DBSCAN_MP_Flag
        )
        VALUES (
            %s, %s, %s, %s, %s, %s, %s,
            %s, %s, %s, %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s,
            %s, %s
        )
        """,
        (
            int(row["CustomerID"]),
            str(row["Country"]),
            float(row["Length"]),
            float(row["Recency"]),
            float(row["Frequency"]),
            float(row["Monetary"]),
            float(row["Periodicity"]),
            float(row["Length_log"]),
            float(row["Recency_log"]),
            float(row["Frequency_log"]),
            float(row["Monetary_log"]),
            float(row["Periodicity_log"]),
            int(row["KMeans_LRFMP_Cluster"]),
            str(row["DBSCAN_LRFMP_Flag"]),
            int(row["KMeans_LR_Cluster"]),
            str(row["DBSCAN_LR_Flag"]),
            int(row["KMeans_LF_Cluster"]),
            str(row["DBSCAN_LF_Flag"]),
            int(row["KMeans_LM_Cluster"]),
            str(row["DBSCAN_LM_Flag"]),
            int(row["KMeans_FP_Cluster"]),
            str(row["DBSCAN_FP_Flag"]),
            int(row["KMeans_MP_Cluster"]),
            str(row["DBSCAN_MP_Flag"]),
        ),
    )

conn.commit()
cursor.close()
conn.close()

print("✅ K-Means clustering + DBSCAN outlier flags inserted successfully!")
