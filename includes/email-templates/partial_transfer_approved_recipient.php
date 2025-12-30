<!DOCTYPE html>
<html>
<body>
    <h2>Partial Transfer Received</h2>
    <p>Dear <?php echo $user_name; ?>,</p>
    
    <p>You have received a portion of land through a partial transfer.</p>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <h3>Transfer Details:</h3>
        <p><strong>Transfer ID:</strong> <?php echo $transfer_id; ?></p>
        <p><strong>Original Parcel:</strong> <?php echo $parcel_no; ?></p>
        <p><strong>Your New Parcel:</strong> <?php echo $new_parcel_no; ?></p>
        <p><strong>Size Received:</strong> <?php echo $transfer_size; ?> acres</p>
        <p><strong>Location:</strong> <?php echo $location; ?></p>
        <p><strong>Approved By:</strong> <?php echo $reviewer_name; ?></p>
        <p><strong>Date:</strong> <?php echo $decision_date; ?></p>
    </div>
    
    <p>The land is now registered under your name. You can view it in your land records.</p>
    
    <p>Best regards,<br>
    <?php echo $site_name; ?> Team</p>
</body>
</html>