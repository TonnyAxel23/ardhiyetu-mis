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
        <h2 style="color: #2e86ab;">New Land Transfer Request</h2>
        
        <p>Dear <?php echo $user_name; ?>,</p>
        
        <p>A new land transfer request has been submitted and requires your review.</p>
        
        <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #2e86ab; margin: 20px 0;">
            <h3 style="margin-top: 0;">Transfer Details:</h3>
            <p><strong>Reference Number:</strong> #<?php echo $reference_no; ?></p>
            <p><strong>Parcel Number:</strong> <?php echo $parcel_no; ?></p>
            <p><strong>Location:</strong> <?php echo $location; ?></p>
            <p><strong>From:</strong> <?php echo $from_user_name ?? 'Previous Owner'; ?></p>
            <p><strong>To:</strong> <?php echo $to_user_name ?? 'New Owner'; ?></p>
            <p><strong>Submitted On:</strong> <?php echo $submission_date; ?></p>
        </div>
        
        <p>Please log in to the admin panel to review this transfer request.</p>
        
        <p>Thank you,<br>
        The <?php echo $site_name; ?> System</p>
    </div>
</body>
</html>