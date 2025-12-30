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
        <h2 style="color: #2e86ab;">Land Transfer Request Submitted</h2>
        
        <p>Dear <?php echo $user_name; ?>,</p>
        
        <p><?php echo $message; ?></p>
        
        <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #2e86ab; margin: 20px 0;">
            <h3 style="margin-top: 0;">Transfer Details:</h3>
            <p><strong>Reference Number:</strong> #<?php echo $reference_no; ?></p>
            <p><strong>Parcel Number:</strong> <?php echo $parcel_no; ?></p>
            <p><strong>Location:</strong> <?php echo $location; ?></p>
            <p><strong>Submitted On:</strong> <?php echo $submission_date; ?></p>
            <p><strong>Status:</strong> <?php echo $status; ?></p>
        </div>
        
        <p>The transfer request is now awaiting administrative review. You will be notified once a decision has been made.</p>
        
        <p>If you have any questions, please contact us at <a href="mailto:<?php echo $admin_email; ?>"><?php echo $admin_email; ?></a>.</p>
        
        <p>Thank you,<br>
        The <?php echo $site_name; ?> Team</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 12px; color: #777;">
            This is an automated message from <?php echo $site_name; ?>. Please do not reply to this email.
        </p>
    </div>
</body>
</html>