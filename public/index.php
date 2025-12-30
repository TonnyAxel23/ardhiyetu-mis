<?php
require_once '../includes/init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArdhiYetu - Digital Land Management System</title>
    <meta name="description" content="ArdhiYetu is a comprehensive digital land administration system for Kenya, streamlining land registration, ownership verification, and transfer processes.">
    <meta name="keywords" content="land management, land registration, Kenya land, digital land, property management">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #2E86AB;
            --primary-dark: #1C5D7F;
            --primary-light: #5BA8D3;
            --secondary: #F4A261;
            --secondary-dark: #D17A2C;
            --accent: #E76F51;
            --light: #F8F9FA;
            --dark: #2C3E50;
            --success: #27AE60;
            --warning: #F39C12;
            --danger: #E74C3C;
            --gray: #95A5A6;
            --gray-light: #ECF0F1;
            --shadow: 0 20px 40px rgba(0,0,0,0.1);
            --shadow-lg: 0 30px 60px rgba(0,0,0,0.15);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            --gradient: linear-gradient(135deg, #2E86AB 0%, #1C5D7F 100%);
            --gradient-light: linear-gradient(135deg, #5BA8D3 0%, #2E86AB 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            background-color: var(--light);
            color: var(--dark);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-light);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        /* Header & Navigation */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            transition: var(--transition);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .header.scrolled {
            padding: 10px 0;
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: var(--gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            transition: var(--transition);
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 40px;
        }
        
        .nav-link {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: var(--transition);
            position: relative;
        }
        
        .nav-link:hover {
            color: var(--primary);
            background: rgba(46, 134, 171, 0.1);
        }
        
        .nav-link.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
        }
        
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            border: 2px solid transparent;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .btn-large {
            padding: 16px 40px;
            font-size: 18px;
            border-radius: 12px;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--dark);
            cursor: pointer;
        }
        
        /* Hero Section */
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(46, 134, 171, 0.05) 0%, rgba(241, 248, 251, 0.8) 100%);
            position: relative;
            overflow: hidden;
            padding-top: 120px;
            display: flex;
            align-items: center;
        }
        
        .hero-bg {
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80') center/cover no-repeat;
            clip-path: polygon(25% 0%, 100% 0%, 100% 100%, 0% 100%);
        }
        
        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .hero-text {
            animation: fadeInUp 1s ease;
        }
        
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(46, 134, 171, 0.1);
            color: var(--primary);
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .hero-badge i {
            font-size: 18px;
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            color: var(--gray);
            margin-bottom: 30px;
            font-weight: 400;
        }
        
        .hero-description {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 600px;
        }
        
        .hero-stats {
            display: flex;
            gap: 40px;
            margin-top: 50px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            display: block;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 500;
        }
        
        .hero-actions {
            display: flex;
            gap: 20px;
            margin-top: 40px;
        }
        
        .hero-image {
            animation: float 3s ease-in-out infinite;
            position: relative;
        }
        
        .hero-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-lg);
            animation: slideInRight 1s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        /* Features Section */
        .features {
            padding: 120px 0;
            background: white;
            position: relative;
        }
        
        .section-header {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 80px;
        }
        
        .section-subtitle {
            color: var(--primary);
            font-weight: 600;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }
        
        .section-title {
            font-size: 3rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .section-description {
            font-size: 1.2rem;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient);
            opacity: 0;
            transition: var(--transition);
        }
        
        .feature-card:hover::before {
            opacity: 1;
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 32px;
            transition: var(--transition);
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .feature-description {
            color: var(--gray);
            font-size: 1rem;
            line-height: 1.6;
        }
        
        /* Services Section */
        .services {
            padding: 120px 0;
            background: linear-gradient(135deg, rgba(46, 134, 171, 0.03) 0%, rgba(241, 248, 251, 0.8) 100%);
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .service-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-light);
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        
        .service-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 25px;
        }
        
        .service-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .service-description {
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .service-features {
            list-style: none;
            margin-top: 20px;
        }
        
        .service-features li {
            padding: 8px 0;
            color: var(--dark);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .service-features li i {
            color: var(--success);
            font-size: 14px;
        }
        
        /* Statistics Section */
        .stats {
            padding: 100px 0;
            background: var(--gradient);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
            opacity: 0.1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }
        
        .stat-card {
            text-align: center;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.2);
        }
        
        .stat-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1;
        }
        
        .stat-text {
            font-size: 1.2rem;
            font-weight: 500;
            opacity: 0.9;
        }
        
        /* Testimonials Section */
        .testimonials {
            padding: 120px 0;
            background: white;
        }
        
        .testimonials-slider {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            margin: 20px;
            position: relative;
            border: 1px solid var(--gray-light);
        }
        
        .testimonial-content {
            font-size: 1.1rem;
            color: var(--dark);
            line-height: 1.8;
            margin-bottom: 30px;
            font-style: italic;
            position: relative;
        }
        
        .testimonial-content::before {
            content: '"';
            font-size: 5rem;
            color: var(--primary);
            opacity: 0.2;
            position: absolute;
            top: -20px;
            left: -10px;
            font-family: serif;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .author-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
        }
        
        .author-info h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .author-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Marketplace Section */
        .marketplace {
            padding: 120px 0;
            background: var(--light);
        }
        
        .marketplace-filters {
            max-width: 1400px;
            margin: 0 auto 50px;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            padding: 10px 15px;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            width: 300px;
        }
        
        .search-btn {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .listings-grid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .listing-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .listing-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        
        .listing-image {
            height: 200px;
            background: var(--gray-light);
            position: relative;
        }
        
        .listing-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .listing-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .listing-featured {
            background: var(--accent);
        }
        
        .listing-content {
            padding: 20px;
        }
        
        .listing-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .listing-location {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .listing-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .listing-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .listing-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-view {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
        }
        
        .btn-offer {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: var(--success);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn-offer:hover {
            background: #219653;
        }
        
        .loading-listings {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px;
            color: var(--gray);
        }
        
        .marketplace-cta {
            text-align: center;
            margin-top: 60px;
        }
        
        .marketplace-cta h3 {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .no-listings {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px;
            color: var(--gray);
        }
        
        /* CTA Section */
        .cta {
            padding: 120px 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
            opacity: 0.1;
        }
        
        .cta-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }
        
        .cta-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .cta-description {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .cta .btn-primary {
            background: white;
            color: var(--primary);
        }
        
        .cta .btn-primary:hover {
            background: var(--gray-light);
            transform: translateY(-3px);
        }
        
        .cta .btn-secondary {
            background: transparent;
            color: white;
            border-color: white;
        }
        
        .cta .btn-secondary:hover {
            background: white;
            color: var(--primary);
        }
        
        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 80px 0 30px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 60px;
            max-width: 1400px;
            margin: 0 auto 60px;
            padding: 0 20px;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .footer-logo-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
        }
        
        .footer-logo-text {
            font-size: 24px;
            font-weight: 800;
            color: white;
        }
        
        .footer-description {
            color: var(--gray);
            margin-bottom: 30px;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .social-link:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }
        
        .footer-heading {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            margin-bottom: 25px;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 15px;
        }
        
        .footer-links a {
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-links a i {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .contact-info {
            list-style: none;
        }
        
        .contact-info li {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .contact-info i {
            color: var(--primary);
            font-size: 18px;
            margin-top: 3px;
        }
        
        .contact-info span {
            color: var(--gray);
            line-height: 1.6;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .footer-bottom a {
            color: var(--primary);
            text-decoration: none;
        }
        
        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        
        .animate-on-scroll.animated {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--gradient);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
            box-shadow: var(--shadow);
        }
        
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 3.5rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 992px) {
            .nav-menu {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .hero-bg {
                display: none;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .hero-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .features-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
            
            .marketplace-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box input {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.8rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .cta-title {
                font-size: 2.5rem;
            }
            
            .stat-number {
                font-size: 2.8rem;
            }
            
            .hero-stats {
                flex-wrap: wrap;
                gap: 30px;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .cta-buttons .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .listings-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .hero-title {
                font-size: 2.2rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 15px;
            }
            
            .btn-large {
                padding: 14px 30px;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }
        
        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            top: 80px;
            left: 0;
            width: 100%;
            background: white;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-100%);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 999;
        }
        
        .mobile-menu.active {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        .mobile-menu .nav-link {
            display: block;
            padding: 15px;
            border-bottom: 1px solid var(--gray-light);
            font-size: 18px;
        }
        
        .mobile-menu .nav-actions {
            flex-direction: column;
            margin-top: 20px;
            gap: 10px;
        }
        
        .mobile-menu .nav-actions .btn {
            width: 100%;
            justify-content: center;
        }
        
        /* Loading Animation */
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        .loading.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>
    
    <!-- Header -->
    <header class="header" id="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-landmark"></i>
                </div>
                <span class="logo-text">ArdhiYetu</span>
            </a>
            
            <nav class="nav-menu">
                <a href="#home" class="nav-link active">Home</a>
                <a href="#features" class="nav-link">Features</a>
                <a href="#services" class="nav-link">Services</a>
                <a href="#marketplace" class="nav-link">Marketplace</a>
                <a href="#testimonials" class="nav-link">Testimonials</a>
                <a href="#contact" class="nav-link">Contact</a>
            </nav>
            
            <div class="nav-actions">
                <?php if(is_logged_in()): ?>
                    <?php if(is_admin()): ?>
                        <a href="admin/index.php" class="btn btn-primary">
                            <i class="fas fa-user-shield"></i> Admin Panel
                        </a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
            
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <a href="#home" class="nav-link active">Home</a>
            <a href="#features" class="nav-link">Features</a>
            <a href="#services" class="nav-link">Services</a>
            <a href="#marketplace" class="nav-link">Marketplace</a>
            <a href="#testimonials" class="nav-link">Testimonials</a>
            <a href="#contact" class="nav-link">Contact</a>
            
            <div class="nav-actions">
                <?php if(is_logged_in()): ?>
                    <?php if(is_admin()): ?>
                        <a href="admin/index.php" class="btn btn-primary">
                            <i class="fas fa-user-shield"></i> Admin Panel
                        </a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-bg"></div>
        <div class="hero-content">
            <div class="hero-text">
                <div class="hero-badge">
                    <i class="fas fa-rocket"></i>
                    <span>Digital Land Management Platform</span>
                </div>
                
                <h1 class="hero-title animate-on-scroll">
                    Revolutionizing Land Administration in Kenya
                </h1>
                
                <p class="hero-subtitle animate-on-scroll" data-delay="0.2s">
                    Secure, Transparent, and Efficient Land Management System
                </p>
                
                <p class="hero-description animate-on-scroll" data-delay="0.4s">
                    ArdhiYetu is a comprehensive digital platform that transforms traditional land 
                    administration processes. Streamline land registration, ownership verification, 
                    and transfer procedures with our innovative technology solutions.
                </p>
                
                <div class="hero-actions animate-on-scroll" data-delay="0.6s">
                    <?php if(!is_logged_in()): ?>
                        <a href="register.php" class="btn btn-primary btn-large">
                            <i class="fas fa-rocket"></i> Get Started Free
                        </a>
                        <a href="#features" class="btn btn-secondary btn-large">
                            <i class="fas fa-play-circle"></i> Watch Demo
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-primary btn-large">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                        <a href="user/my-lands.php" class="btn btn-secondary btn-large">
                            <i class="fas fa-landmark"></i> My Land Records
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="hero-stats animate-on-scroll" data-delay="0.8s">
                    <div class="stat-item">
                        <span class="stat-number" data-target="5000">0</span>
                        <span class="stat-label">Land Records</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" data-target="2500">0</span>
                        <span class="stat-label">Registered Users</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" data-target="99">0</span>
                        <span class="stat-label">% Satisfaction</span>
                    </div>
                </div>
            </div>
            
            <div class="hero-image">
                <div class="hero-card animate-on-scroll" data-delay="1s">
                    <img src="https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" 
                         alt="Land Management Dashboard" 
                         style="width: 100%; border-radius: 15px; margin-bottom: 20px;">
                    <h3 style="color: var(--dark); margin-bottom: 10px;">Live Dashboard Preview</h3>
                    <p style="color: var(--gray); font-size: 0.9rem;">
                        Real-time monitoring of land records and transactions
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header animate-on-scroll">
            <span class="section-subtitle">Why Choose ArdhiYetu</span>
            <h2 class="section-title">Powerful Features for Modern Land Management</h2>
            <p class="section-description">
                Our platform combines cutting-edge technology with user-friendly design 
                to deliver an unparalleled land administration experience.
            </p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card animate-on-scroll">
                <div class="feature-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <h3 class="feature-title">Digital Land Registration</h3>
                <p class="feature-description">
                    Register land parcels digitally with secure document upload, 
                    automated verification, and real-time status tracking.
                </p>
            </div>
            
            <div class="feature-card animate-on-scroll" data-delay="0.2s">
                <div class="feature-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <h3 class="feature-title">Ownership Transfer</h3>
                <p class="feature-description">
                    Process land ownership transfers online with multi-level approval 
                    workflow and automated notifications.
                </p>
            </div>
            
            <div class="feature-card animate-on-scroll" data-delay="0.4s">
                <div class="feature-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3 class="feature-title">GIS Mapping</h3>
                <p class="feature-description">
                    Visualize land parcels with boundary details using integrated 
                    mapping tools and GPS coordinates.
                </p>
            </div>
            
            <div class="feature-card animate-on-scroll" data-delay="0.6s">
                <div class="feature-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="feature-title">Real-time Notifications</h3>
                <p class="feature-description">
                    Receive instant updates on application status, approvals, 
                    and important announcements via multiple channels.
                </p>
            </div>
            
            <div class="feature-card animate-on-scroll" data-delay="0.8s">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="feature-title">Bank-Level Security</h3>
                <p class="feature-description">
                    Advanced encryption, two-factor authentication, and secure 
                    data storage to protect your sensitive information.
                </p>
            </div>
            
            <div class="feature-card animate-on-scroll" data-delay="1s">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3 class="feature-title">Mobile First Design</h3>
                <p class="feature-description">
                    Access all features from any device with our responsive 
                    design and dedicated mobile applications.
                </p>
            </div>
        </div>
    </section>
    
    <!-- Services Section -->
    <section class="services" id="services">
        <div class="section-header animate-on-scroll">
            <span class="section-subtitle">Our Solutions</span>
            <h2 class="section-title">Comprehensive Land Administration Services</h2>
            <p class="section-description">
                End-to-end solutions designed to meet all your land management needs.
            </p>
        </div>
        
        <div class="services-grid">
            <div class="service-card animate-on-scroll">
                <i class="fas fa-search service-icon"></i>
                <h3 class="service-title">Land Verification</h3>
                <p class="service-description">
                    Instantly verify land ownership and history with our secure 
                    database and blockchain technology.
                </p>
                <ul class="service-features">
                    <li><i class="fas fa-check"></i> Instant ownership verification</li>
                    <li><i class="fas fa-check"></i> Historical record tracking</li>
                    <li><i class="fas fa-check"></i> Dispute resolution support</li>
                </ul>
            </div>
            
            <div class="service-card animate-on-scroll" data-delay="0.2s">
                <i class="fas fa-print service-icon"></i>
                <h3 class="service-title">Document Generation</h3>
                <p class="service-description">
                    Generate and print official land documents with QR codes 
                    for instant verification and authentication.
                </p>
                <ul class="service-features">
                    <li><i class="fas fa-check"></i> Customizable templates</li>
                    <li><i class="fas fa-check"></i> QR code integration</li>
                    <li><i class="fas fa-check"></i> Digital signatures</li>
                </ul>
            </div>
            
            <div class="service-card animate-on-scroll" data-delay="0.4s">
                <i class="fas fa-chart-line service-icon"></i>
                <h3 class="service-title">Reports & Analytics</h3>
                <p class="service-description">
                    Access detailed reports and analytics on land transactions, 
                    market trends, and administrative performance.
                </p>
                <ul class="service-features">
                    <li><i class="fas fa-check"></i> Custom report generation</li>
                    <li><i class="fas fa-check"></i> Real-time analytics</li>
                    <li><i class="fas fa-check"></i> Market insights</li>
                </ul>
            </div>
        </div>
    </section>
    
    <!-- Statistics Section -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <i class="fas fa-landmark stat-icon"></i>
                <div class="stat-number" data-target="5000">0</div>
                <div class="stat-text">Land Records Managed</div>
            </div>
            
            <div class="stat-card animate-on-scroll" data-delay="0.1s">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-number" data-target="2500">0</div>
                <div class="stat-text">Registered Users</div>
            </div>
            
            <div class="stat-card animate-on-scroll" data-delay="0.2s">
                <i class="fas fa-exchange-alt stat-icon"></i>
                <div class="stat-number" data-target="1200">0</div>
                <div class="stat-text">Completed Transfers</div>
            </div>
            
            <div class="stat-card animate-on-scroll" data-delay="0.3s">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-number" data-target="75">0</div>
                <div class="stat-text">% Faster Processing</div>
            </div>
        </div>
    </section>
    
    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="section-header animate-on-scroll">
            <span class="section-subtitle">Success Stories</span>
            <h2 class="section-title">What Our Users Say</h2>
            <p class="section-description">
                Hear from landowners, legal professionals, and government officials 
                who have transformed their land management processes.
            </p>
        </div>
        
        <div class="testimonials-slider">
            <div class="testimonial-card animate-on-scroll">
                <div class="testimonial-content">
                    "ArdhiYetu has revolutionized how we handle land transactions. 
                    The process is now transparent, efficient, and completely digital. 
                    What used to take weeks now happens in days!"
                </div>
                <div class="testimonial-author">
                   <img src="images/tonny.jpg" 
                   alt="Tonny Axels"
                   class="author-avatar">

                    <div class="author-info">
                        <h4>Tonny Axels</h4>
                        <p>Landowner, Nairobi</p>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card animate-on-scroll" data-delay="0.2s">
                <div class="testimonial-content">
                    "As a legal practitioner, the verification system saves me hours 
                    of work. I can instantly verify land ownership and history. 
                    Highly recommended for any legal professional!"
                </div>
                <div class="testimonial-author">
                    <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80" 
                         alt="Sarah Mwangi" 
                         class="author-avatar">
                    <div class="author-info">
                        <h4>Sarah Mwangi</h4>
                        <p>Advocate, Mombasa</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Marketplace Section -->
    <section class="marketplace" id="marketplace">
        <div class="section-header animate-on-scroll">
            <span class="section-subtitle">Land Marketplace</span>
            <h2 class="section-title">Featured Lands for Sale/Lease</h2>
            <p class="section-description">
                Browse available land listings. Interested? Register to make an offer!
            </p>
        </div>
        
        <div class="marketplace-filters">
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All Listings</button>
                <button class="filter-btn" data-filter="sale">For Sale</button>
                <button class="filter-btn" data-filter="lease">For Lease</button>
                <button class="filter-btn" data-filter="featured">Featured</button>
            </div>
            <div class="search-box">
                <input type="text" placeholder="Search by location, price..." id="marketplaceSearch">
                <button class="search-btn"><i class="fas fa-search"></i></button>
            </div>
        </div>
        
        <div class="listings-grid" id="listingsGrid">
            <!-- Listings will be loaded via AJAX -->
            <div class="loading-listings">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading available listings...</p>
            </div>
        </div>
        
        <div class="marketplace-cta animate-on-scroll">
            <h3>Want to list your land?</h3>
            <p>Reach thousands of potential buyers or tenants</p>
            <?php if(is_logged_in()): ?>
                <a href="user/list-land.php" class="btn btn-primary btn-large">
                    <i class="fas fa-plus-circle"></i> List Your Land
                </a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-large">
                    <i class="fas fa-user-plus"></i> Register to List
                </a>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta" id="contact">
        <div class="cta-content">
            <h2 class="cta-title animate-on-scroll">Ready to Transform Your Land Management?</h2>
            <p class="cta-description animate-on-scroll" data-delay="0.2s">
                Join thousands of Kenyans who have streamlined their land administration 
                processes with ArdhiYetu. Experience the future of land management today.
            </p>
            <div class="cta-buttons animate-on-scroll" data-delay="0.4s">
                <?php if(!is_logged_in()): ?>
                    <a href="register.php" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus"></i> Create Free Account
                    </a>
                    <a href="login.php" class="btn btn-secondary btn-large">
                        <i class="fas fa-headset"></i> Schedule a Demo
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-primary btn-large">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                    <a href="user/my-lands.php" class="btn btn-secondary btn-large">
                        <i class="fas fa-landmark"></i> Manage Land Records
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <div class="footer-logo">
                    <div class="footer-logo-icon">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <span class="footer-logo-text">ArdhiYetu</span>
                </div>
                <p class="footer-description">
                    Digital Land Administration System for Kenya. Making land 
                    management transparent, efficient, and accessible for everyone.
                </p>
                <div class="social-links">
                    <a href="#" class="social-link">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
            
            <div class="footer-col">
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                    <li><a href="#services"><i class="fas fa-chevron-right"></i> Services</a></li>
                    <li><a href="#marketplace"><i class="fas fa-chevron-right"></i> Marketplace</a></li>
                    <li><a href="#testimonials"><i class="fas fa-chevron-right"></i> Testimonials</a></li>
                    <li><a href="#contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h3 class="footer-heading">Resources</h3>
                <ul class="footer-links">
                    <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                    <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQs</a></li>
                    <li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li>
                    <li><a href="docs.php"><i class="fas fa-chevron-right"></i> Documentation</a></li>
                    <li><a href="updates.php"><i class="fas fa-chevron-right"></i> Updates</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h3 class="footer-heading">Contact Us</h3>
                <ul class="contact-info">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>ArdhiYetu Headquarters<br>Kibabii University<br>Bungoma, Kenya</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span>0700 000 000<br>0711 111 111</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>info@ardhiyetu.go.ke<br>support@ardhiyetu.go.ke</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System. All rights reserved. | 
               <a href="privacy.php">Privacy Policy</a> | 
               <a href="terms.php">Terms of Service</a>
            </p>
            <p style="margin-top: 10px; font-size: 0.8rem;">
                Developed by JAJIT Technologists • Powered by Innovation
            </p>
        </div>
    </footer>
    
    <!-- Back to Top Button -->
    <a href="#home" class="back-to-top" id="backToTop">
        <i class="fas fa-chevron-up"></i>
    </a>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize page
            initPage();
            
            // Setup scroll animations
            setupScrollAnimations();
            
            // Setup mobile menu
            setupMobileMenu();
            
            // Setup back to top button
            setupBackToTop();
            
            // Setup counters
            setupCounters();
            
            // Setup scroll effects
            setupScrollEffects();
            
            // Setup marketplace
            setupMarketplace();
        });
        
        function initPage() {
            // Hide loading screen after page loads
            window.addEventListener('load', function() {
                setTimeout(() => {
                    document.getElementById('loading').classList.add('hidden');
                }, 500);
            });
            
            // Animate hero on load
            const heroElements = document.querySelectorAll('.animate-on-scroll');
            heroElements.forEach(el => {
                if (el.getAttribute('data-delay')) {
                    setTimeout(() => {
                        el.classList.add('animated');
                    }, parseFloat(el.getAttribute('data-delay')) * 1000);
                } else {
                    el.classList.add('animated');
                }
            });
        }
        
        function setupScrollAnimations() {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const delay = entry.target.getAttribute('data-delay') || 0;
                        setTimeout(() => {
                            entry.target.classList.add('animated');
                        }, parseFloat(delay) * 1000);
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observe all animate-on-scroll elements
            document.querySelectorAll('.animate-on-scroll').forEach(el => {
                observer.observe(el);
            });
        }
        
        function setupMobileMenu() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const navLinks = document.querySelectorAll('.nav-link');
            
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('active');
                mobileMenuBtn.innerHTML = mobileMenu.classList.contains('active') 
                    ? '<i class="fas fa-times"></i>' 
                    : '<i class="fas fa-bars"></i>';
            });
            
            // Close menu when clicking a link
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                    mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                });
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!mobileMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    mobileMenu.classList.remove('active');
                    mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                }
            });
        }
        
        function setupBackToTop() {
            const backToTop = document.getElementById('backToTop');
            
            window.addEventListener('scroll', () => {
                if (window.pageYOffset > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });
            
            // Smooth scroll to top
            backToTop.addEventListener('click', (e) => {
                e.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
        
        function setupCounters() {
            const counters = document.querySelectorAll('.stat-number');
            const speed = 200;
            
            counters.forEach(counter => {
                const updateCount = () => {
                    const target = +counter.getAttribute('data-target');
                    const count = +counter.innerText.replace(/,/g, '');
                    const increment = target / speed;
                    
                    if (count < target) {
                        counter.innerText = Math.ceil(count + increment).toLocaleString();
                        setTimeout(updateCount, 1);
                    } else {
                        counter.innerText = target.toLocaleString();
                    }
                };
                
                // Start counter when in view
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateCount();
                            observer.unobserve(entry.target);
                        }
                    });
                });
                
                observer.observe(counter);
            });
        }
        
        function setupScrollEffects() {
            const header = document.getElementById('header');
            
            window.addEventListener('scroll', () => {
                if (window.pageYOffset > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
            
            // Smooth scroll for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Update active nav link on scroll
            const sections = document.querySelectorAll('section[id]');
            
            window.addEventListener('scroll', () => {
                let current = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    
                    if (scrollY >= sectionTop - 100) {
                        current = section.getAttribute('id');
                    }
                });
                
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${current}`) {
                        link.classList.add('active');
                    }
                });
            });
        }
        
        function setupMarketplace() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const searchInput = document.getElementById('marketplaceSearch');
            const searchBtn = document.querySelector('.search-btn');
            
            // Load initial listings
            loadListings();
            
            // Filter button functionality
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(b => b.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Load filtered listings
                    const filter = this.getAttribute('data-filter');
                    loadListings(filter);
                });
            });
            
            // Search functionality
            searchBtn.addEventListener('click', () => {
                const query = searchInput.value.trim();
                loadListings(null, query);
            });
            
            // Enter key in search
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const query = searchInput.value.trim();
                    loadListings(null, query);
                }
            });
        }
        
        function loadListings(filter = 'all', searchQuery = '') {
            const listingsGrid = document.getElementById('listingsGrid');
            
            // Show loading state
            listingsGrid.innerHTML = `
                <div class="loading-listings">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading available listings...</p>
                </div>
            `;
            
            // In production, you would fetch from your API
            // For demo purposes, we'll simulate with dummy data
            setTimeout(() => {
                const listings = getDummyListings(filter, searchQuery);
                
                if (listings.length === 0) {
                    listingsGrid.innerHTML = `
                        <div class="no-listings">
                            <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 20px;"></i>
                            <h3>No listings found</h3>
                            <p>Try adjusting your filters or check back later.</p>
                        </div>
                    `;
                    return;
                }
                
                listingsGrid.innerHTML = listings.map(listing => `
                    <div class="listing-card animate-on-scroll" data-listing-type="${listing.type}">
                        <div class="listing-image">
                            <img src="${listing.image}" alt="${listing.title}">
                            <div class="listing-badge ${listing.featured ? 'listing-featured' : ''}">
                                ${listing.featured ? 'FEATURED' : listing.type.toUpperCase()}
                            </div>
                        </div>
                        <div class="listing-content">
                            <h3 class="listing-title">${listing.title}</h3>
                            <div class="listing-location">
                                <i class="fas fa-map-marker-alt"></i>
                                ${listing.location}
                            </div>
                            <div class="listing-price">${listing.price}</div>
                            <div class="listing-meta">
                                <span><i class="fas fa-expand-arrows-alt"></i> ${listing.size}</span>
                                <span><i class="far fa-calendar"></i> ${listing.date}</span>
                            </div>
                            <div class="listing-actions">
                                <a href="listing-details.php?id=${listing.id}" class="btn-view">
                                    <i class="far fa-eye"></i> View Details
                                </a>
                                <?php if(is_logged_in()): ?>
                                    <a href="user/make-offer.php?id=${listing.id}" class="btn-offer">
                                        <i class="fas fa-handshake"></i> Make Offer
                                    </a>
                                <?php else: ?>
                                    <a href="register.php" class="btn-offer">
                                        <i class="fas fa-user-plus"></i> Register to Offer
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                `).join('');
                
                // Observe new listings for animation
                setupScrollAnimations();
            }, 1000);
        }
        
        function getDummyListings(filter, searchQuery) {
            const allListings = [
                {
                    id: 1,
                    title: "5-Acre Agricultural Land",
                    location: "Kiambu County",
                    price: "KSh 8,500,000",
                    size: "5 Acres",
                    date: "3 days ago",
                    type: "sale",
                    featured: true,
                    image: "https://images.unsplash.com/photo-1500382017468-9049fed747ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
                },
                {
                    id: 2,
                    title: "Commercial Plot for Lease",
                    location: "Nairobi CBD",
                    price: "KSh 150,000/month",
                    size: "0.5 Acres",
                    date: "1 week ago",
                    type: "lease",
                    featured: false,
                    image: "https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
                },
                {
                    id: 3,
                    title: "Residential Plot",
                    location: "Kajiado County",
                    price: "KSh 3,200,000",
                    size: "1 Acre",
                    date: "2 days ago",
                    type: "sale",
                    featured: false,
                    image: "https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
                },
                {
                    id: 4,
                    title: "10-Acre Ranch Land",
                    location: "Laikipia County",
                    price: "KSh 12,000,000",
                    size: "10 Acres",
                    date: "5 days ago",
                    type: "sale",
                    featured: true,
                    image: "https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
                },
                {
                    id: 5,
                    title: "Industrial Plot for Lease",
                    location: "Mombasa",
                    price: "KSh 250,000/month",
                    size: "2 Acres",
                    date: "2 weeks ago",
                    type: "lease",
                    featured: false,
                    image: "https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
                },
                {
                    id: 6,
                    title: "Beachfront Property",
                    location: "Diani, Kwale",
                    price: "KSh 25,000,000",
                    size: "3 Acres",
                    date: "1 day ago",
                    type: "sale",
                    featured: true,
                    image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80"
                }
            ];
            
            // Apply filters
            let filtered = allListings;
            
            if (filter !== 'all') {
                if (filter === 'featured') {
                    filtered = allListings.filter(listing => listing.featured);
                } else {
                    filtered = allListings.filter(listing => listing.type === filter);
                }
            }
            
            // Apply search query
            if (searchQuery) {
                const query = searchQuery.toLowerCase();
                filtered = filtered.filter(listing => 
                    listing.title.toLowerCase().includes(query) ||
                    listing.location.toLowerCase().includes(query) ||
                    listing.price.toLowerCase().includes(query)
                );
            }
            
            return filtered;
        }
        
        // Add parallax effect to hero
        function setupParallax() {
            const heroBg = document.querySelector('.hero-bg');
            
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const rate = scrolled * -0.5;
                heroBg.style.transform = `translate3d(0, ${rate}px, 0)`;
            });
        }
        
        // Initialize parallax on large screens
        if (window.innerWidth > 992) {
            setupParallax();
        }
        
        // Add typing effect to hero title
        function setupTypingEffect() {
            const heroTitle = document.querySelector('.hero-title');
            const originalText = heroTitle.textContent;
            const words = originalText.split(' ');
            heroTitle.innerHTML = '';
            
            let wordIndex = 0;
            let charIndex = 0;
            let currentWord = '';
            let isDeleting = false;
            
            function type() {
                if (wordIndex < words.length) {
                    if (!isDeleting && charIndex <= words[wordIndex].length) {
                        currentWord = words[wordIndex].substring(0, charIndex);
                        charIndex++;
                        setTimeout(type, 100);
                    } else if (isDeleting && charIndex >= 0) {
                        currentWord = words[wordIndex].substring(0, charIndex);
                        charIndex--;
                        setTimeout(type, 50);
                    } else {
                        isDeleting = !isDeleting;
                        if (!isDeleting) {
                            wordIndex++;
                        }
                        setTimeout(type, 500);
                    }
                    
                    heroTitle.innerHTML = currentWord + '<span class="cursor">|</span>';
                }
            }
            
            // Start typing effect after page loads
            setTimeout(type, 1000);
        }
        
        // Uncomment to enable typing effect
        // setupTypingEffect();
        
        // Add cursor style for typing effect
        const style = document.createElement('style');
        style.textContent = `
            .cursor {
                animation: blink 1s infinite;
                color: var(--primary);
            }
            
            @keyframes blink {
                0%, 100% { opacity: 1; }
                50% { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Add hover effects to cards
        document.querySelectorAll('.feature-card, .service-card, .stat-card, .listing-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Add click effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                // Create ripple effect
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.5);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                `;
                
                this.appendChild(ripple);
                
                // Remove ripple after animation
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Add ripple animation
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .btn {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(rippleStyle);
        
        // Add newsletter subscription
        function setupNewsletter() {
            const newsletterForm = document.createElement('div');
            newsletterForm.innerHTML = `
                <div style="background: var(--gradient-light); padding: 40px; border-radius: 20px; margin: 40px 0; text-align: center; color: white;">
                    <h3 style="margin-bottom: 15px; font-size: 1.8rem;">Stay Updated</h3>
                    <p style="margin-bottom: 25px; opacity: 0.9;">Subscribe to our newsletter for the latest updates and features.</p>
                    <form id="newsletterForm" style="max-width: 500px; margin: 0 auto; display: flex; gap: 10px;">
                        <input type="email" placeholder="Enter your email" required 
                               style="flex: 1; padding: 15px 20px; border: none; border-radius: 10px; font-size: 16px;">
                        <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                            <i class="fas fa-paper-plane"></i> Subscribe
                        </button>
                    </form>
                </div>
            `;
            
            // Insert before footer
            document.querySelector('.footer').insertAdjacentElement('beforebegin', newsletterForm);
            
            // Handle form submission
            document.getElementById('newsletterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const email = this.querySelector('input[type="email"]').value;
                
                // Show success message
                showToast('Thank you for subscribing to our newsletter!', 'success');
                this.reset();
                
                // In production, you would send this to your backend
                console.log('Newsletter subscription:', email);
            });
        }
        
        // Uncomment to enable newsletter
        // setupNewsletter();
        
        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            toast.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                background: white;
                padding: 15px 25px;
                border-radius: 12px;
                box-shadow: var(--shadow-lg);
                display: flex;
                align-items: center;
                gap: 12px;
                z-index: 9999;
                animation: slideInRight 0.3s ease;
                border-left: 5px solid ${type === 'success' ? '#27AE60' : type === 'error' ? '#E74C3C' : '#3498DB'};
                max-width: 400px;
            `;
            
            document.body.appendChild(toast);
            
            // Remove after 5 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
            
            // Add keyframes if not already added
            if (!document.getElementById('toast-animations')) {
                const style = document.createElement('style');
                style.id = 'toast-animations';
                style.textContent = `
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOutRight {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Preload images for better performance
        function preloadImages() {
            const images = [
                'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80',
                'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1500382017468-9049fed747ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80',
                'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'
            ];
            
            images.forEach(src => {
                const img = new Image();
                img.src = src;
            });
        }
        
        preloadImages();
    </script>
</body>
</html>