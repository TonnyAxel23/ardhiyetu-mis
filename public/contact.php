<?php
require_once '../includes/init.php';

$error = '';
$success = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address';
    } else {
        // Save contact message to database
        $insert_sql = "INSERT INTO contact_messages (name, email, subject, message) 
                      VALUES ('$name', '$email', '$subject', '$message')";
        
        if (mysqli_query($conn, $insert_sql)) {
            $success = 'Thank you for contacting us! We will get back to you soon.';
            
            // Clear form
            $_POST = array();
            
            // Send email notification (simulated - in production, use PHPMailer or similar)
            $admin_email = 'support@ardhiyetu.go.ke';
            $email_subject = "New Contact Message: $subject";
            $email_message = "You have received a new contact message:\n\n";
            $email_message .= "Name: $name\n";
            $email_message .= "Email: $email\n";
            $email_message .= "Subject: $subject\n";
            $email_message .= "Message:\n$message\n";
            
            // In production, uncomment this:
            // mail($admin_email, $email_subject, $email_message);
            
            // Log activity if user is logged in
            if (isset($_SESSION['user_id'])) {
                log_activity($_SESSION['user_id'], 'contact_form', 'Submitted contact form');
            }
        } else {
            $error = 'Failed to submit message. Please try again.';
        }
    }
}

// Get FAQ data
$faqs = [
    [
        'question' => 'How do I register land on ArdhiYetu?',
        'answer' => 'To register land, create an account, go to "My Lands" section, click "Register New Land", fill in the required details including parcel number, location, size, and upload supporting documents. Your application will be reviewed by our team.'
    ],
    [
        'question' => 'How long does land transfer take?',
        'answer' => 'Land transfer typically takes 3-5 working days for review and approval. The exact time depends on document verification and any additional requirements. You can track the status in your dashboard.'
    ],
    [
        'question' => 'Is my personal information secure?',
        'answer' => 'Yes, we use bank-level encryption and security measures to protect your data. We comply with Kenya\'s Data Protection Act (2019) and never share your information with third parties without consent.'
    ],
    [
        'question' => 'What documents do I need for land registration?',
        'answer' => 'You need your national ID, proof of ownership (title deed, sale agreement, etc.), and any boundary documents. For transfer, you need the transfer agreement and both parties\' identification documents.'
    ],
    [
        'question' => 'Can I access ArdhiYetu on mobile?',
        'answer' => 'Yes, ArdhiYetu is fully responsive and works on all devices including smartphones and tablets. You can also download documents and submit requests from your mobile device.'
    ],
    [
        'question' => 'What if I forget my password?',
        'answer' => 'Click "Forgot Password" on the login page. Enter your email address and follow the instructions sent to your email to reset your password.'
    ]
];

// Get contact information
$contact_info = [
    'email' => 'support@ardhiyetu.go.ke',
    'phone' => '0700 000 000',
    'phone2' => '0711 111 111',
    'address' => 'ArdhiYetu Headquarters,<br>Kibabii University,<br>Bungoma, Kenya',
    'working_hours' => 'Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 9:00 AM - 1:00 PM<br>Sunday: Closed',
    'emergency_contact' => 'For urgent matters outside working hours, contact: 0722 222 222'
];

// Create contact_messages table if not exists
$table_sql = "CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at TIMESTAMP NULL,
    admin_notes TEXT
)";

mysqli_query($conn, $table_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - ArdhiYetu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-landmark"></i> ArdhiYetu
            </a>
            <div class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="services.php"><i class="fas fa-cogs"></i> Services</a>
                <a href="contact.php" class="active"><i class="fas fa-phone"></i> Contact</a>
                <?php if(is_logged_in()): ?>
                    <?php if(is_admin()): ?>
                        <a href="admin/index.php" class="btn"><i class="fas fa-user-shield"></i> Admin</a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="register.php" class="btn secondary"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <main class="contact-page">
        <div class="container">
            <!-- Hero Section -->
            <section class="contact-hero">
                <div class="hero-content">
                    <h1>Contact Us</h1>
                    <p>We're here to help you with all your land administration needs</p>
                    <div class="hero-stats">
                        <div class="stat">
                            <i class="fas fa-headset"></i>
                            <div>
                                <h3>24/7 Support</h3>
                                <p>Available for urgent matters</p>
                            </div>
                        </div>
                        <div class="stat">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h3>Quick Response</h3>
                                <p>Typically within 2 hours</p>
                            </div>
                        </div>
                        <div class="stat">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h3>95% Satisfaction</h3>
                                <p>Based on user feedback</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hero-image">
                    <i class="fas fa-comments"></i>
                </div>
            </section>

            <!-- Contact Information -->
            <section class="contact-info-section">
                <div class="section-header">
                    <h2>Get In Touch</h2>
                    <p>Choose your preferred way to contact us</p>
                </div>
                
                <div class="contact-methods">
                    <div class="contact-method">
                        <div class="method-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="method-content">
                            <h3>Call Us</h3>
                            <p><?php echo $contact_info['phone']; ?></p>
                            <p><?php echo $contact_info['phone2']; ?></p>
                            <small>Available during working hours</small>
                        </div>
                        <a href="tel:<?php echo str_replace(' ', '', $contact_info['phone']); ?>" class="btn">
                            <i class="fas fa-phone"></i> Call Now
                        </a>
                    </div>
                    
                    <div class="contact-method">
                        <div class="method-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="method-content">
                            <h3>Email Us</h3>
                            <p><?php echo $contact_info['email']; ?></p>
                            <small>We respond within 24 hours</small>
                        </div>
                        <a href="mailto:<?php echo $contact_info['email']; ?>" class="btn">
                            <i class="fas fa-envelope"></i> Send Email
                        </a>
                    </div>
                    
                    <div class="contact-method">
                        <div class="method-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="method-content">
                            <h3>Visit Us</h3>
                            <p><?php echo $contact_info['address']; ?></p>
                            <small><?php echo $contact_info['working_hours']; ?></small>
                        </div>
                        <button class="btn" onclick="showMap()">
                            <i class="fas fa-map"></i> View Map
                        </button>
                    </div>
                </div>
            </section>

            <!-- Contact Form -->
            <section class="contact-form-section">
                <div class="form-container">
                    <div class="form-header">
                        <h2>Send Us a Message</h2>
                        <p>Fill out the form below and we'll get back to you as soon as possible</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="contact-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">
                                    <i class="fas fa-user"></i> Full Name *
                                </label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       required 
                                       placeholder="Enter your full name"
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : (isset($_SESSION['name']) ? $_SESSION['name'] : ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i> Email Address *
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       required 
                                       placeholder="Enter your email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($_SESSION['email']) ? $_SESSION['email'] : ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">
                                <i class="fas fa-tag"></i> Subject *
                            </label>
                            <select id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="Land Registration" <?php echo isset($_POST['subject']) && $_POST['subject'] == 'Land Registration' ? 'selected' : ''; ?>>Land Registration</option>
                                <option value="Land Transfer" <?php echo isset($_POST['subject']) && $_POST['subject'] == 'Land Transfer' ? 'selected' : ''; ?>>Land Transfer</option>
                                <option value="Technical Support" <?php echo isset($_POST['subject']) && $_POST['subject'] == 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                                <option value="Account Issues" <?php echo isset($_POST['subject']) && $_POST['subject'] == 'Account Issues' ? 'selected' : ''; ?>>Account Issues</option>
                                <option value="Feedback" <?php echo isset($_POST['subject']) && $_POST['subject'] == 'Feedback' ? 'selected' : ''; ?>>Feedback</option>
                                <option value="Other" <?php echo isset($_POST['subject']) && $_POST['subject'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">
                                <i class="fas fa-comment"></i> Message *
                            </label>
                            <textarea id="message" 
                                      name="message" 
                                      required 
                                      rows="6" 
                                      placeholder="Please describe your issue or question in detail..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> / 2000 characters
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox">
                                <input type="checkbox" name="subscribe" id="subscribe" checked>
                                <span>Subscribe to newsletters and updates</span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn large">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                            <button type="reset" class="btn secondary">
                                <i class="fas fa-redo"></i> Clear Form
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="form-sidebar">
                    <div class="sidebar-card">
                        <h3><i class="fas fa-question-circle"></i> Need Immediate Help?</h3>
                        <p>For urgent matters, call our emergency line:</p>
                        <div class="emergency-contact">
                            <i class="fas fa-phone-alt"></i>
                            <div>
                                <strong><?php echo $contact_info['emergency_contact']; ?></strong>
                                <small>Available 24/7 for critical issues</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sidebar-card">
                        <h3><i class="fas fa-lightbulb"></i> Before You Contact</h3>
                        <ul>
                            <li>Check our FAQ section below</li>
                            <li>Have your reference number ready</li>
                            <li>Include relevant documents if needed</li>
                            <li>Be specific about your issue</li>
                        </ul>
                    </div>
                    
                    <div class="sidebar-card">
                        <h3><i class="fas fa-comments"></i> Live Chat</h3>
                        <p>Chat with our support team in real-time:</p>
                        <button class="btn" onclick="openChat()">
                            <i class="fas fa-comment-dots"></i> Start Chat
                        </button>
                        <small>Available: Mon-Fri, 8 AM - 5 PM</small>
                    </div>
                </div>
            </section>

            <!-- FAQ Section -->
            <section class="faq-section">
                <div class="section-header">
                    <h2>Frequently Asked Questions</h2>
                    <p>Find quick answers to common questions</p>
                </div>
                
                <div class="faq-container">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(<?php echo $index; ?>)">
                                <h3><?php echo $faq['question']; ?></h3>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer" id="faq-answer-<?php echo $index; ?>">
                                <p><?php echo $faq['answer']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Offices Section -->
            <section class="offices-section">
                <div class="section-header">
                    <h2>Our Offices</h2>
                    <p>Visit us at any of our locations</p>
                </div>
                
                <div class="offices-grid">
                    <div class="office-card">
                        <div class="office-header">
                            <i class="fas fa-building"></i>
                            <h3>Headquarters</h3>
                        </div>
                        <div class="office-body">
                            <p><i class="fas fa-map-marker-alt"></i> Kibabii University, Bungoma</p>
                            <p><i class="fas fa-phone"></i> 0700 000 000</p>
                            <p><i class="fas fa-envelope"></i> hq@ardhiyetu.go.ke</p>
                            <p><i class="fas fa-clock"></i> Mon-Fri: 8 AM - 5 PM</p>
                        </div>
                    </div>
                    
                    <div class="office-card">
                        <div class="office-header">
                            <i class="fas fa-city"></i>
                            <h3>Nairobi Office</h3>
                        </div>
                        <div class="office-body">
                            <p><i class="fas fa-map-marker-alt"></i> Upper Hill, Nairobi</p>
                            <p><i class="fas fa-phone"></i> 020 123 4567</p>
                            <p><i class="fas fa-envelope"></i> nairobi@ardhiyetu.go.ke</p>
                            <p><i class="fas fa-clock"></i> Mon-Fri: 8 AM - 5 PM</p>
                        </div>
                    </div>
                    
                    <div class="office-card">
                        <div class="office-header">
                            <i class="fas fa-umbrella-beach"></i>
                            <h3>Mombasa Office</h3>
                        </div>
                        <div class="office-body">
                            <p><i class="fas fa-map-marker-alt"></i> Nyali, Mombasa</p>
                            <p><i class="fas fa-phone"></i> 041 123 4567</p>
                            <p><i class="fas fa-envelope"></i> mombasa@ardhiyetu.go.ke</p>
                            <p><i class="fas fa-clock"></i> Mon-Fri: 8 AM - 5 PM</p>
                        </div>
                    </div>
                    
                    <div class="office-card">
                        <div class="office-header">
                            <i class="fas fa-lake"></i>
                            <h3>Kisumu Office</h3>
                        </div>
                        <div class="office-body">
                            <p><i class="fas fa-map-marker-alt"></i> Milimani, Kisumu</p>
                            <p><i class="fas fa-phone"></i> 057 123 4567</p>
                            <p><i class="fas fa-envelope"></i> kisumu@ardhiyetu.go.ke</p>
                            <p><i class="fas fa-clock"></i> Mon-Fri: 8 AM - 5 PM</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Map Section -->
            <section class="map-section">
                <div class="section-header">
                    <h2>Find Us</h2>
                    <p>Visit our headquarters in Bungoma</p>
                </div>
                
                <div class="map-container" id="map">
                    <!-- In production, add Google Maps here -->
                    <div class="map-placeholder">
                        <i class="fas fa-map-marked-alt"></i>
                        <h3>Interactive Map</h3>
                        <p>Map would display here in production</p>
                        <p>Coordinates: 0.5961° N, 34.5630° E</p>
                        <a href="https://maps.google.com/?q=Kibabii+University+Bungoma" 
                           target="_blank" 
                           class="btn">
                            <i class="fas fa-external-link-alt"></i> Open in Google Maps
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Live Chat Widget (Hidden by default) -->
    <div class="chat-widget" id="chatWidget">
        <div class="chat-header">
            <h3><i class="fas fa-comments"></i> Live Chat Support</h3>
            <button class="chat-close" onclick="closeChat()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chat-body">
            <div class="chat-messages" id="chatMessages">
                <div class="message bot">
                    <div class="message-content">
                        <p>Hello! How can I help you today?</p>
                    </div>
                    <div class="message-time">Just now</div>
                </div>
            </div>
            <div class="chat-input">
                <input type="text" 
                       id="chatInput" 
                       placeholder="Type your message..." 
                       onkeypress="handleChatKeypress(event)">
                <button onclick="sendChatMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-landmark"></i> ArdhiYetu</h3>
                    <p>Digital Land Administration System for Kenya</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="services.php">Services</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p><i class="fas fa-envelope"></i> <?php echo $contact_info['email']; ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo $contact_info['phone']; ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo strip_tags($contact_info['address']); ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Character counter for message textarea
            const messageTextarea = document.getElementById('message');
            const charCount = document.getElementById('charCount');
            
            if (messageTextarea && charCount) {
                messageTextarea.addEventListener('input', function() {
                    const length = this.value.length;
                    charCount.textContent = length;
                    
                    if (length > 2000) {
                        charCount.style.color = '#e74c3c';
                        this.value = this.value.substring(0, 2000);
                    } else if (length > 1800) {
                        charCount.style.color = '#f39c12';
                    } else {
                        charCount.style.color = '#27ae60';
                    }
                });
                
                // Trigger on load
                messageTextarea.dispatchEvent(new Event('input'));
            }
            
            // Form subject change handler
            const subjectSelect = document.getElementById('subject');
            if (subjectSelect) {
                subjectSelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        // Create custom subject input
                        const existingCustom = document.getElementById('customSubject');
                        if (!existingCustom) {
                            const customInput = document.createElement('input');
                            customInput.type = 'text';
                            customInput.id = 'customSubject';
                            customInput.name = 'custom_subject';
                            customInput.required = true;
                            customInput.placeholder = 'Please specify your subject';
                            customInput.style.marginTop = '0.5rem';
                            
                            this.parentNode.appendChild(customInput);
                        }
                    } else {
                        const customInput = document.getElementById('customSubject');
                        if (customInput) {
                            customInput.remove();
                        }
                    }
                });
            }
        });
        
        // FAQ toggle function
        function toggleFaq(index) {
            const answer = document.getElementById('faq-answer-' + index);
            const icon = answer.previousElementSibling.querySelector('i');
            
            if (answer.style.display === 'block') {
                answer.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                answer.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }
        
        // Chat functions
        function openChat() {
            document.getElementById('chatWidget').style.display = 'block';
            document.getElementById('chatInput').focus();
        }
        
        function closeChat() {
            document.getElementById('chatWidget').style.display = 'none';
        }
        
        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                // Add user message
                addChatMessage(message, 'user');
                input.value = '';
                
                // Simulate bot response after delay
                setTimeout(() => {
                    const responses = [
                        "I understand. Let me check that for you.",
                        "Thanks for your question. Our team will get back to you soon.",
                        "I can help with that. Please provide more details.",
                        "That's a common question. Let me find the best answer for you.",
                        "I'll connect you with a specialist for that issue."
                    ];
                    const randomResponse = responses[Math.floor(Math.random() * responses.length)];
                    addChatMessage(randomResponse, 'bot');
                }, 1000);
            }
        }
        
        function handleChatKeypress(event) {
            if (event.key === 'Enter') {
                sendChatMessage();
            }
        }
        
        function addChatMessage(text, sender) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + sender;
            
            const now = new Date();
            const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                             now.getMinutes().toString().padStart(2, '0');
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    <p>${text}</p>
                </div>
                <div class="message-time">${timeString}</div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Map function
        function showMap() {
            alert('Map would open here in production. You would see our location at Kibabii University, Bungoma.');
            // In production, this would open a modal with Google Maps
        }
        
        // Form validation
        function validateContactForm() {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!name) {
                showToast('Please enter your name', 'error');
                return false;
            }
            
            if (!email || !emailRegex.test(email)) {
                showToast('Please enter a valid email address', 'error');
                return false;
            }
            
            if (!subject) {
                showToast('Please select a subject', 'error');
                return false;
            }
            
            if (!message || message.length < 10) {
                showToast('Please enter a message with at least 10 characters', 'error');
                return false;
            }
            
            return true;
        }
    </script>
    <style>
        /* Additional styles for contact page */
        .contact-page {
            padding: 2rem 0;
        }
        
        /* Hero Section */
        .contact-hero {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 3rem;
            border-radius: 10px;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3rem;
        }
        
        .contact-hero h1 {
            color: white;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero-content {
            flex: 1;
        }
        
        .hero-image {
            font-size: 8rem;
            opacity: 0.2;
        }
        
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .hero-stats .stat {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .hero-stats .stat i {
            font-size: 2rem;
        }
        
        .hero-stats .stat h3 {
            color: white;
            margin-bottom: 0.25rem;
            font-size: 1.25rem;
        }
        
        .hero-stats .stat p {
            margin: 0;
            opacity: 0.8;
        }
        
        /* Contact Methods */
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }
        
        .contact-method {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .contact-method:hover {
            transform: translateY(-5px);
        }
        
        .method-icon {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 1.5rem;
        }
        
        .method-content {
            flex: 1;
            margin-bottom: 1.5rem;
        }
        
        .method-content h3 {
            margin-bottom: 1rem;
        }
        
        .method-content p {
            margin: 0.5rem 0;
        }
        
        .method-content small {
            color: #666;
        }
        
        /* Contact Form Section */
        .contact-form-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
            margin: 3rem 0;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            padding: 2.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .form-header {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .char-count {
            text-align: right;
            margin-top: 0.5rem;
            color: #666;
            font-size: 0.875rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        /* Sidebar */
        .form-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .sidebar-card h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .emergency-contact {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .emergency-contact i {
            font-size: 1.5rem;
            color: #f39c12;
        }
        
        /* FAQ Section */
        .faq-section {
            margin: 4rem 0;
        }
        
        .faq-container {
            max-width: 800px;
            margin: 2rem auto 0;
        }
        
        .faq-item {
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            transition: background 0.3s ease;
        }
        
        .faq-question:hover {
            background: #e9ecef;
        }
        
        .faq-question h3 {
            margin: 0;
            flex: 1;
        }
        
        .faq-answer {
            padding: 1.5rem;
            display: none;
            border-top: 1px solid #dee2e6;
            background: white;
        }
        
        /* Offices Section */
        .offices-section {
            margin: 4rem 0;
        }
        
        .offices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .office-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .office-card:hover {
            transform: translateY(-5px);
        }
        
        .office-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .office-header i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .office-header h3 {
            color: white;
            margin: 0;
        }
        
        .office-body {
            padding: 1.5rem;
        }
        
        .office-body p {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0.75rem 0;
            color: #666;
        }
        
        .office-body i {
            color: #3498db;
            width: 20px;
        }
        
        /* Map Section */
        .map-section {
            margin: 4rem 0;
        }
        
        .map-container {
            background: #f8f9fa;
            border-radius: 10px;
            height: 400px;
            margin-top: 2rem;
            overflow: hidden;
            position: relative;
        }
        
        .map-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #666;
        }
        
        .map-placeholder i {
            font-size: 4rem;
            color: #3498db;
            margin-bottom: 1rem;
        }
        
        /* Chat Widget */
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header h3 {
            color: white;
            margin: 0;
            font-size: 1rem;
        }
        
        .chat-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.25rem;
        }
        
        .chat-body {
            height: 400px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-messages {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 1rem;
            max-width: 80%;
        }
        
        .message.user {
            margin-left: auto;
        }
        
        .message.bot {
            margin-right: auto;
        }
        
        .message-content {
            padding: 0.75rem 1rem;
            border-radius: 15px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .message.user .message-content {
            background: #3498db;
            color: white;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: #666;
            margin-top: 0.25rem;
            text-align: right;
        }
        
        .chat-input {
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: 0.5rem;
        }
        
        .chat-input input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            outline: none;
        }
        
        .chat-input input:focus {
            border-color: #3498db;
        }
        
        .chat-input button {
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Section Headers */
        .section-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .section-header h2 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .section-header p {
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Social Links */
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #2c3e50;
            color: white;
            border-radius: 50%;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        
        .social-links a:hover {
            background: #3498db;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .contact-hero {
                flex-direction: column;
                text-align: center;
                padding: 2rem;
            }
            
            .hero-stats {
                grid-template-columns: 1fr;
            }
            
            .contact-form-section {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-image {
                order: -1;
                font-size: 5rem;
            }
            
            .chat-widget {
                width: calc(100% - 40px);
                bottom: 10px;
                right: 10px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions button {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .contact-methods {
                grid-template-columns: 1fr;
            }
            
            .offices-grid {
                grid-template-columns: 1fr;
            }
            
            .contact-hero h1 {
                font-size: 2rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
        }
    </style>
</body>
</html>