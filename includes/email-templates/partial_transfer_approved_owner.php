<!DOCTYPE html>
<html>
<body>
    <h2>Partial Transfer Approved</h2>
    <p>Dear <?php echo $user_name; ?>,</p>
    
    <p>Your partial land transfer has been approved by the administration.</p>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <h3>Transfer Details:</h3>
        <p><strong>Transfer ID:</strong> <?php echo $transfer_id; ?></p>
        <p><strong>Original Parcel:</strong> <?php echo $parcel_no; ?></p>
        <p><strong>New Parcel Created:</strong> <?php echo $new_parcel_no; ?></p>
        <p><strong>Size Transferred:</strong> <?php echo $transfer_size; ?> acres</p>
        <p><strong>Size Remaining:</strong> <?php echo $remaining_size; ?> acres</p>
        <p><strong>Location:</strong> <?php echo $location; ?></p>
        <p><strong>Approved By:</strong> <?php echo $reviewer_name; ?></p>
        <p><strong>Date:</strong> <?php echo $decision_date; ?></p>
    </div>
    
    <?php if ($review_notes): ?>
    <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin: 10px 0;">
        <strong>Review Notes:</strong><br>
        <?php echo nl2br($review_notes); ?>
    </div>
    <?php endif; ?>
    
    <p>You can view your updated land records in your account.</p>
    
    <p>Best regards,<br>
    <?php echo $site_name; ?> Team</p>
</body>
</html>