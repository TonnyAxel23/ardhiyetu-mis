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
        <h2 style="color: #e74c3c;">Land Transfer Request Rejected</h2>
        
        <p>Dear <?php echo $user_name; ?>,</p>
        
        <p>We regret to inform you that your land transfer request has been rejected.</p>
        
        <div style="background: #fadbd8; padding: 15px; border-left: 4px solid #e74c3c; margin: 20px 0;">
            <h3 style="margin-top: 0;">Transfer Details:</h3>
            <p><strong>Reference Number:</strong> #<?php echo $reference_no; ?></p>
            <p><strong>Parcel Number:</strong> <?php echo $parcel_no; ?></p>
            <p><strong>Location:</strong> <?php echo $location; ?></p>
            <p><strong>Decision Date:</strong> <?php echo $decision_date; ?></p>
            <p><strong>Reviewed By:</strong> <?php echo $reviewer_name; ?></p>
            <?php if (!empty($review_notes)): ?>
            <p><strong>Review Notes:</strong><br><?php echo nl2br($review_notes); ?></p>
            <?php endif; ?>
        </div>
        
        <p>The land parcel remains registered under your name. No changes have been made to the ownership records.</p>
        
        <p>If you have questions about this decision or wish to appeal, please contact us at <a href="mailto:<?php echo $admin_email; ?>"><?php echo $admin_email; ?></a>.</p>
        
        <p>Sincerely,<br>
        The <?php echo $site_name; ?> Team</p>
    </div>
</body>
</html>