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
        <h2 style="color: #27ae60;">🎉 Land Ownership Transferred!</h2>
        
        <p>Dear <?php echo $user_name; ?>,</p>
        
        <p>We are pleased to inform you that you are now the registered owner of the following land parcel:</p>
        
        <div style="background: #d4edda; padding: 15px; border-left: 4px solid #27ae60; margin: 20px 0;">
            <h3 style="margin-top: 0;">Ownership Details:</h3>
            <p><strong>Reference Number:</strong> #<?php echo $reference_no; ?></p>
            <p><strong>Parcel Number:</strong> <?php echo $parcel_no; ?></p>
            <p><strong>Location:</strong> <?php echo $location; ?></p>
            <p><strong>Transfer Approved On:</strong> <?php echo $decision_date; ?></p>
            <p><strong>Approved By:</strong> <?php echo $reviewer_name; ?></p>
        </div>
        
        <p>The land transfer has been completed successfully and you are now the legal owner of this property.</p>
        
        <p>You can now manage this land parcel through your account on our platform.</p>
        
        <p>If you have any questions, please contact us at <a href="mailto:<?php echo $admin_email; ?>"><?php echo $admin_email; ?></a>.</p>
        
        <p>Congratulations!<br>
        The <?php echo $site_name; ?> Team</p>
    </div>
</body>
</html>