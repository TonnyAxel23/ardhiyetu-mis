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
        <h2 style="color: #3498db;">Land Transfer Approved</h2>
        
        <p>Dear <?php echo $user_name; ?>,</p>
        
        <p>Your land transfer request has been approved and the ownership has been successfully transferred.</p>
        
        <div style="background: #d6eaf8; padding: 15px; border-left: 4px solid #3498db; margin: 20px 0;">
            <h3 style="margin-top: 0;">Transfer Details:</h3>
            <p><strong>Reference Number:</strong> #<?php echo $reference_no; ?></p>
            <p><strong>Parcel Number:</strong> <?php echo $parcel_no; ?></p>
            <p><strong>Location:</strong> <?php echo $location; ?></p>
            <p><strong>Transferred To:</strong> <?php echo $to_user_name ?? 'New Owner'; ?></p>
            <p><strong>Approved On:</strong> <?php echo $decision_date; ?></p>
            <p><strong>Approved By:</strong> <?php echo $reviewer_name; ?></p>
        </div>
        
        <p>You are no longer the owner of this land parcel. All ownership rights have been transferred to the new owner.</p>
        
        <p>If you believe this transfer was made in error, please contact us immediately at <a href="mailto:<?php echo $admin_email; ?>"><?php echo $admin_email; ?></a>.</p>
        
        <p>Thank you for using our services,<br>
        The <?php echo $site_name; ?> Team</p>
    </div>
</body>
</html>