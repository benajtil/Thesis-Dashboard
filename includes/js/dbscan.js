function generateDBSCANInsights(details, segmentation, flagCol) {
    var dbscanFlag = details[flagCol];
    var insights = "";
    var suggestions = "";
    
    if (dbscanFlag === "Outlier") {
        if (segmentation === "rfm") {

            if (parseFloat(details.Recency) > 365) {
                insights += "Customer inactive for over a year may be an anomaly.\n";
                suggestions += "Consider a reactivation campaign or verify account status.\n";
            }
            if (parseFloat(details.Frequency) <= 1) {
                insights += "Very low purchase frequency detected.\n";
                suggestions += "Target with incentives to boost frequency.\n";
            }
            if (parseFloat(details.Monetary) > 1000) {
                insights += "Exceptionally high spending compared to peers.\n";
                suggestions += "Review for potential one-off purchases.\n";
            }
            if (parseFloat(details.Recency) <= 7) {
                insights += "Extremely recent purchase might be atypical.\n";
                suggestions += "Monitor for issues like returns or anomalies.\n";
            }
            if (parseFloat(details.Monetary) < 10) {
                insights += "Spending is unusually low for a registered customer.\n";
                suggestions += "Double-check if this is a guest account or requires targeted promotion.\n";
            }
            
            if (parseFloat(details.Frequency) > 50 && (parseFloat(details.Monetary) / parseFloat(details.Frequency)) < 5) {
                insights += "Very high frequency with extremely low average order value detected.\n";
                suggestions += "Investigate potential data entry errors or discount abuse.\n";
            }
            if (parseFloat(details.Recency) > 180 && parseFloat(details.Frequency) > 10 && parseFloat(details.Monetary) < 100) {
                insights += "Inactivity combined with moderate frequency but low spending.\n";
                suggestions += "Examine for bulk orders or intermittent buying patterns that may be atypical.\n";
            }
            if (parseFloat(details.Recency) >= 30 && parseFloat(details.Recency) <= 90 && parseFloat(details.Frequency) > 5 && parseFloat(details.Monetary) < 50) {
                insights += "Multiple recent low-value purchases detected.\n";
                suggestions += "Review if these are erroneous entries or deliberate low-value orders.\n";
            }
            if (parseFloat(details.Recency) < 14 && parseFloat(details.Frequency) > 20 && (parseFloat(details.Monetary) / parseFloat(details.Frequency)) < 2) {
                insights += "High frequency but extremely low average spend in recent orders.\n";
                suggestions += "Investigate for potential fraud or pricing issues.\n";
            }
        } else {

            if (parseFloat(details.Length) > 730) {
                insights += "Customer relationship exceeds typical duration.\n";
                suggestions += "Review loyalty benefits to maintain engagement.\n";
            }
            if (parseFloat(details.Periodicity) > 120) {
                insights += "High gap between orders observed.\n";
                suggestions += "Send targeted reminders or offers to reduce the gap.\n";
            }
            if (parseFloat(details.Monetary) > 5000) {
                insights += "Outstandingly high spending detected.\n";
                suggestions += "Evaluate for VIP treatment or personalized engagement.\n";
            }
            if (parseFloat(details.Frequency) < 3) {
                insights += "Low frequency despite a long relationship.\n";
                suggestions += "Investigate potential satisfaction issues or competitive influences.\n";
            }
            if (parseFloat(details.Periodicity) < 5) {
                insights += "Very short intervals between orders indicate potential data irregularity.\n";
                suggestions += "Verify order authenticity and overall customer engagement.\n";
            }
            

            if (parseFloat(details.Length) > 500 && parseFloat(details.Recency) < 30) {
                insights += "Long-term customer with a very recent purchase; exceptional loyalty.\n";
                suggestions += "Consider rewarding with premium loyalty benefits.\n";
            }
            if (parseFloat(details.Frequency) > 50 && (parseFloat(details.Monetary) / parseFloat(details.Frequency)) < 10) {
                insights += "Extremely high frequency with low average spend per order.\n";
                suggestions += "Investigate potential issues with order values or data errors.\n";
            }
            if (parseFloat(details.Recency) > 500 && parseFloat(details.Length) < 300) {
                insights += "Long period since last purchase relative to overall relationship duration.\n";
                suggestions += "Re-engage customer with personalized offers and communications.\n";
            }
            if ((parseFloat(details.Monetary) / parseFloat(details.Frequency)) > 500) {
                insights += "Very high average order value detected, suggesting VIP status or data anomaly.\n";
                suggestions += "Validate order data and consider personalized high-value engagement.\n";
            }
            if (parseFloat(details.Periodicity) > (parseFloat(details.Length) / 2)) {
                insights += "Order gap is disproportionately high relative to the overall relationship length.\n";
                suggestions += "Review customer engagement strategies and external factors affecting order frequency.\n";
            }
            if (parseFloat(details.Recency) < 14 && parseFloat(details.Frequency) > 10 && parseFloat(details.Monetary) < 100) {
                insights += "Recent orders with very low spending despite high frequency.\n";
                suggestions += "Investigate for potential data inconsistencies or pricing issues.\n";
            }
        }
    } else {
        insights = "Customer behavior aligns with the majority.";
        suggestions = "No additional DBSCAN-based interventions required.";
    }
    
    return { status: dbscanFlag, insights: insights.trim(), suggestions: suggestions.trim() };
}
