<?php
if (!isset($subject)) $subject = "Default Subject Here";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo $subject; ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #f39c12;">Land Transfer Under Review</h2>
        
        <p>Dear <?php echo $user_name; ?>,</p>
        
        <p>Your land transfer request is now under administrative review.</p>
        
        <div style="background: #fef9e7; padding: 15px; border-left: 4px solid #f39c12; margin: 20px 0;">
            <h3 style="margin-top: 0;">Transfer Details:</h3>
            <p><strong>Reference Number:</strong> #<?php echo $reference_no; ?></p>
            <p><strong>Parcel Number:</strong> <?php echo $parcel_no; ?></p>
            <p><strong>Location:</strong> <?php echo $location; ?></p>
            <p><strong>Status:</strong> Under Review</p>
            <p><strong>Review Started:</strong> <?php echo $decision_date; ?></p>
        </div>
        
        <p>Our administrative team is currently reviewing your transfer request. This process typically takes 3-5 business days.</p>
        
        <p>You will be notified via email once a decision has been made on your transfer request.</p>
        
        <p>If you have any questions, please contact us at <a href="mailto:<?php echo $admin_email; ?>"><?php echo $admin_email; ?></a>.</p>
        
        <p>Thank you for your patience,<br>
        The <?php echo $site_name; ?> Team</p>
    </div>
</body>
</html>

