// insights.js
function generateKMeansInsights(details, segmentation) {
    var insights = "";
    var suggestions = "";
    
    if (segmentation === "rfm") {
        var rec = parseFloat(details.Recency);
        var freq = parseFloat(details.Frequency);
        var mon = parseFloat(details.Monetary);
        

        if (freq <= 1 && mon >= 300) {
            insights += "Only 1 purchase with high total spend. Potential one-time big spender.\n";
            suggestions += "Re-engage with a special offer or VIP invitation.\n";
        }
        if (rec < 7 && freq > 5) {
            insights += "Very recent buyer with multiple purchases in a short period.\n";
            suggestions += "Nudge them with cross-sell or loyalty rewards.\n";
        }
        if (mon > 1000) {
            insights += "High total spending overall.\n";
            suggestions += "Consider a premium membership or personal shopping assistance.\n";
        }
        if (rec > 180 && freq < 3) {
            insights += "Inactive for over half a year and low purchase frequency.\n";
            suggestions += "Send a campaign or survey to understand inactivity.\n";
        }
        if (rec <= 30 && mon < 50) {
            insights += "Recently purchased but with low total spend.\n";
            suggestions += "Suggest budget-friendly product bundles or freebies for next order.\n";
        }
        if (freq >= 10 && mon < 200) {
            insights += "Frequent but low-value purchases.\n";
            suggestions += "Offer volume discounts or product upsell to increase basket size.\n";
        }
        if (rec > 365) {
            insights += "Hasn't purchased in over a year.\n";
            suggestions += "Consider a reactivation campaign with a special discount.\n";
        }
        if (freq >= 20) {
            insights += "Super frequent buyer.\n";
            suggestions += "Reward loyalty with tiered benefits or exclusive early access.\n";
        }
        if (mon < 20) {
            insights += "Extremely low total spend overall.\n";
            suggestions += "Try cross-selling or bigger deals to boost order value.\n";
        }
        if (rec <= 2) {
            insights += "Very recent purchase within last 2 days.\n";
            suggestions += "Send a post-purchase thank-you or invitation for feedback.\n";
        }
        if (freq == 1 && mon < 100) {
            insights += "Single, low-value purchase.\n";
            suggestions += "Offer discount on next purchase to entice a second sale.\n";
        }
        if (rec <= 14 && freq >= 5 && mon > 500) {
            insights += "Active, frequent, and high-spending in the last two weeks.\n";
            suggestions += "Promote VIP or loyalty membership to sustain momentum.\n";
        }
        if (freq > 5 && (mon / freq) < 20) {
            insights += "High frequency but low average order value.\n";
            suggestions += "Bundle promotions or loyalty points per item to raise average spend.\n";
        }
        if (rec > 90 && mon > 300) {
            insights += "Significant spend in the past, but no recent activity for 3+ months.\n";
            suggestions += "Target with an exclusive comeback campaign or limited-time sale.\n";
        }
        if (freq <= 3 && mon > 1000) {
            insights += "Few orders but extremely high total spend.\n";
            suggestions += "Personalized approach: invite to a high-end product preview.\n";
        }
        if (rec < 60 && freq >= 2 && mon < 80) {
            insights += "Multiple small purchases within last 2 months.\n";
            suggestions += "Suggest larger-value bundles or loyalty-based free shipping.\n";
        }
        if (freq >= 15 && (mon / freq) > 100) {
            insights += "Frequent buyer with high average order value.\n";
            suggestions += "Consider top-tier VIP perks or early access to new releases.\n";
        }
        if (rec <= 7 && mon > 5000) {
            insights += "A very recent, extremely high-spend purchase.\n";
            suggestions += "Follow up with premium membership invitation or a personal thank-you call.\n";
        }
        if (rec > 270 && freq == 1 && mon < 100) {
            insights += "Has not come back for 9+ months after a small single purchase.\n";
            suggestions += "Send a re-introduction campaign or survey to learn why they left.\n";
        }
        if ((mon > 2000) && (freq > 3) && (rec < 60)) {
            insights += "Big spender and frequent buyer in the last 2 months.\n";
            suggestions += "Roll out the red carpet with loyalty upgrades or free shipping.\n";
        }
        

        if (rec >= 7 && rec <= 30 && freq >= 3 && freq <= 10 && mon >= 50 && mon <= 300) {
            insights += "Steady customer with moderate activity and spending.\n";
            suggestions += "Maintain engagement with regular updates and loyalty points.\n";
        }
        if (rec >= 30 && rec <= 90 && freq >= 2 && mon >= 50 && mon < 200) {
            insights += "Moderate recency with consistent but low-value orders.\n";
            suggestions += "Offer incentives to gradually increase order value.\n";
        }
        if (rec > 90 && freq >= 10 && mon >= 200 && mon < 500) {
            insights += "Decent frequency with moderate spending, but purchase recency is lagging.\n";
            suggestions += "Boost engagement with time-limited offers or reactivation campaigns.\n";
        }
        if (rec < 10 && freq < 3 && mon < 50) {
            insights += "New customer with low frequency and spending.\n";
            suggestions += "Nurture the relationship with introductory discounts and personalized recommendations.\n";
        }
        if (rec > 200 && freq > 20 && mon < 100) {
            insights += "High frequency but extremely low spend after a long inactivity.\n";
            suggestions += "Investigate the cause and encourage higher spend per order.\n";
        }
        if (rec < 15 && freq > 20 && mon > 1000) {
            insights += "Rapid repeat orders with high spending in a short period.\n";
            suggestions += "Reward the customer with exclusive perks or early access to new products.\n";
        }
        if (rec >= 60 && rec < 120 && freq >= 5 && mon > 300 && mon <= 700) {
            insights += "Balanced customer with moderate recency, frequency, and spending.\n";
            suggestions += "Maintain satisfaction with steady loyalty incentives.\n";
        }
        if (rec > 150 && freq < 5 && mon > 500) {
            insights += "Long gap with surprisingly high spending in few orders.\n";
            suggestions += "Engage with personalized outreach and high-value promotions.\n";
        }
        if (rec < 30 && freq >= 10 && mon >= 300 && mon < 600) {
            insights += "Recent active customer with consistent medium spend.\n";
            suggestions += "Encourage upselling with bundled offers.\n";
        }
        if (rec > 300 && freq < 2 && mon < 50) {
            insights += "Significant inactivity with minimal orders.\n";
            suggestions += "Launch a strong reactivation strategy to win back the customer.\n";
        }
        if (insights.trim() === "") {
            insights = "No special RFM patterns detected. Check overall cluster or gather more data.";
        }
        if (suggestions.trim() === "") {
            suggestions = "Maintain regular communication and watch for changes in RFM metrics.";
        }
        
    } else {

        let lengthVal = parseFloat(details.Length);
        let rec = parseFloat(details.Recency);
        let freq = parseFloat(details.Frequency);
        let mon = parseFloat(details.Monetary);
        let per = parseFloat(details.Periodicity);
        

        if (lengthVal > 365 && freq > 5) {
            insights += "Customer for over a year with multiple purchases.\n";
            suggestions += "Consider a loyalty milestone reward or membership.\n";
        }
        if (per > 60) {
            insights += "Long average gap between orders.\n";
            suggestions += "Send periodic reminders or seasonal promotions to reduce intervals.\n";
        }
        if (mon < 50) {
            insights += "Overall spend is quite low.\n";
            suggestions += "Offer deals or upsells to boost average order value.\n";
        }
        if (rec < 14 && freq > 3) {
            insights += "Recently active with multiple orders in 2 weeks.\n";
            suggestions += "Encourage them to join a subscription or monthly plan.\n";
        }
        if (lengthVal < 30 && freq <= 1 && rec < 5) {
            insights += "New customer with minimal purchase activity.\n";
            suggestions += "Provide a warm welcome and highlight top products.\n";
        }
        if (lengthVal > 730) {
            insights += "Long-term relationship (2+ years).\n";
            suggestions += "Send an anniversary thank-you or advanced loyalty perk.\n";
        }
        if (mon > 1000 && per < 30 && rec < 20) {
            insights += "High spend with short intervals between purchases.\n";
            suggestions += "Tailor a frequent-shopper program or premium subscription.\n";
        }
        if (freq >= 20 && lengthVal < 180) {
            insights += "Extremely frequent buyer within first 6 months of relationship.\n";
            suggestions += "Offer top-tier incentives early to solidify loyalty.\n";
        }
        if (rec > 180 && lengthVal > 365) {
            insights += "Long-time customer but hasn't purchased in 6+ months.\n";
            suggestions += "Send a 'Messages or Emails' or loyalty reactivation campaign.\n";
        }
        if (mon > 5000) {
            insights += "Significant total spend over time.\n";
            suggestions += "Personal VIP approach or invite them to exclusive events.\n";
        }
        if (per > 120 && freq > 1) {
            insights += "Multiple purchases but huge gaps between them.\n";
            suggestions += "Encourage more consistent buying with limited-time offers.\n";
        }
        if (lengthVal < 60 && mon > 500 && rec < 25) {
            insights += "Relatively new but already high spending.\n";
            suggestions += "Welcome them with an early big spender incentive.\n";
        }
        if (freq <= 2 && mon <= 100 && lengthVal > 300) {
            insights += "Very low activity and spend over a long relationship.\n";
            suggestions += "Try a targeted reactivation or big discount to spark interest.\n";
        }
        if (rec <= 7 && per < 20) {
            insights += "Recent purchase and short intervals between orders.\n";
            suggestions += "Upsell complementary products or subscription services.\n";
        }
        if (mon / freq > 300 && freq > 1) {
            insights += "Very high average order value across multiple orders.\n";
            suggestions += "Offer premium services or advanced loyalty tiers.\n";
        }
        if (lengthVal > 500 && freq < 3) {
            insights += "Customer for a long time but only a few orders.\n";
            suggestions += "Send exclusive re-engagement or phone-based outreach.\n";
        }
        if (rec > 365 && freq > 5) {
            insights += "Multiple orders historically, but no activity in over a year.\n";
            suggestions += "Personalized 'Come Back' promotion or special event invite.\n";
        }
        if (freq > 30 && mon < 500) {
            insights += "High volume of orders but relatively low total spend.\n";
            suggestions += "Suggest higher-end products or bundling to increase spend per order.\n";
        }
        if (per <= 10 && freq >= 10) {
            insights += "They purchase very frequently (under 10-day gap) with many orders.\n";
            suggestions += "Potential subscription or advanced loyalty benefits to keep them engaged.\n";
        }
        if (lengthVal < 90 && freq > 10 && mon > 1000) {
            insights += "New but extremely active and high-spending within 3 months.\n";
            suggestions += "Immediate VIP-level recognition or personal shopper approach.\n";
        }
        
        if (lengthVal >= 400 && lengthVal <= 600 && rec >= 30 && rec <= 90 && freq >= 5 && freq <= 10 && mon >= 200 && mon <= 600 && per >= 20 && per <= 40) {
            insights += "Steady and consistent customer with balanced metrics.\n";
            suggestions += "Maintain the relationship with regular engagement and personalized offers.\n";
        }
        if (lengthVal < 100 && rec < 14 && freq < 5 && mon < 200 && per < 15) {
            insights += "Very new customer with low activity.\n";
            suggestions += "Introduce them with welcome incentives and guidance on popular products.\n";
        }
        if (lengthVal > 800 && rec > 120 && freq < 4 && mon > 700) {
            insights += "Long-term customer with declining frequency but high spending.\n";
            suggestions += "Investigate for potential issues and offer re-engagement initiatives.\n";
        }
        if (rec >= 30 && rec <= 60 && freq >= 10 && mon >= 500 && per < 20) {
            insights += "Highly engaged customer with frequent, moderate spend orders.\n";
            suggestions += "Reward their loyalty with exclusive benefits or early access sales.\n";
        }
        if (lengthVal > 500 && rec > 200 && freq < 3 && mon < 100) {
            insights += "Long relationship but minimal recent activity.\n";
            suggestions += "Deploy an aggressive reactivation strategy to rekindle interest.\n";
        }
        if (rec < 15 && freq > 15 && mon > 1500 && per < 10) {
            insights += "New, ultra-active customer with high spending potential.\n";
            suggestions += "Fast-track them into a premium loyalty program.\n";
        }
        if (lengthVal > 700 && rec > 100 && freq >= 5 && mon >= 300 && per > 50) {
            insights += "Stable long-term customer with high periodicity between orders.\n";
            suggestions += "Encourage more frequent purchases with time-sensitive offers.\n";
        }
        if (lengthVal < 300 && rec < 30 && freq >= 10 && mon >= 300 && per < 20) {
            insights += "New but rapidly growing customer base.\n";
            suggestions += "Invest in personalized cross-sell strategies to increase order value.\n";
        }
        if (rec > 250 && freq >= 20 && mon < 200) {
            insights += "High order frequency but low spend and long recency period.\n";
            suggestions += "Reassess pricing strategy and explore upselling opportunities.\n";
        }
        if (rec < 7 && freq < 3 && mon > 800) {
            insights += "Recent high-value purchase despite low frequency.\n";
            suggestions += "Ensure post-purchase satisfaction and consider personal follow-up.\n";
        }
        if (insights.trim() === "") {
            insights = "No special LRFMP patterns detected. Check cluster or other data sources.";
        }
        if (suggestions.trim() === "") {
            suggestions = "Maintain consistent communication and track LRFMP changes over time.";
        }
    }
    
    return { insights: insights.trim(), suggestions: suggestions.trim() };
}
