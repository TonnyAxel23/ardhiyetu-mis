<?php
// test-email-simple.php
// Place this in your project root

// Simple test without Composer
require_once __DIR__ . '/includes/EmailSender.php';

echo "<h1>Testing Email System</h1>";

// Test configuration - UPDATE THESE!
$config = [
    'smtp_username' => 'tonnyodhiambo49@gmail.com',  // Your Gmail
    'smtp_password' => 'mjjc bhkp lfuv igth',     // Gmail App Password
    'email_from' => 'tonnyodhiambo707@gmail.com',
    'admin_email' => 'tonnyodhiambo707@gmail.com',
    'debug' => true
];

try {
    $emailSender = new EmailSender($config);
    
    // Test data
    $testData = [
        'user_email' => 'tonnyodhiambo49@gmail.com',  // Change to your email
        'user_name' => 'Test User',
        'reference_no' => 'TEST-' . time(),
        'parcel_no' => 'TEST-PARCEL-001',
        'location' => '123 Test Street, City',
        'to_user_name' => 'New Test Owner',
        'decision_date' => date('F j, Y'),
        'reviewer_name' => 'Admin User',
        'review_notes' => 'This is a test rejection note.',
        'submission_date' => date('F j, Y'),
        'from_user_name' => 'Previous Owner',
        'status' => 'Under Review',
        'message' => 'Your transfer request has been submitted successfully.'
    ];
    
    echo "<h3>Test 1: Transfer Approved (Previous Owner)</h3>";
    $result1 = $emailSender->sendTransferApprovedPrevious($testData);
    echo $result1 ? "✓ Sent successfully!<br>" : "✗ Failed: " . $emailSender->getLastError() . "<br>";
    
    echo "<h3>Test 2: Transfer Approved (New Owner)</h3>";
    $result2 = $emailSender->sendTransferApprovedNew($testData);
    echo $result2 ? "✓ Sent successfully!<br>" : "✗ Failed: " . $emailSender->getLastError() . "<br>";
    
    echo "<h3>Test 3: Transfer Submission</h3>";
    $result3 = $emailSender->sendTransferSubmission($testData);
    echo $result3 ? "✓ Sent successfully!<br>" : "✗ Failed: " . $emailSender->getLastError() . "<br>";
    
    echo "<h3>Test 4: Transfer Rejected</h3>";
    $result4 = $emailSender->sendTransferRejected($testData);
    echo $result4 ? "✓ Sent successfully!<br>" : "✗ Failed: " . $emailSender->getLastError() . "<br>";
    
    echo "<h2>Test Complete!</h2>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>
            <strong>Error:</strong> " . $e->getMessage() . "
          </div>";
}
?>