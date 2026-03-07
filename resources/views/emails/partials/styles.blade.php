<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.5;
        color: #333333;
        background-color: #ffffff;
        margin: 0;
        padding: 0;
    }

    .email-container {
        max-width: 600px;
        margin: 0 auto;
        background-color: #ffffff;
    }

    .email-header {
        background-color: #000000;
        color: white;
        padding: 40px 20px;
        text-align: center;
        border-bottom: 1px solid #e5e5e5;
    }

    .email-header h1 {
        font-size: 24px;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .email-header p {
        font-size: 14px;
        color: #cccccc;
    }

    .order-info {
        background-color: #f8f8f8;
        padding: 20px;
        margin: 0;
        border-bottom: 1px solid #e5e5e5;
    }

    .order-info h2 {
        color: #333333;
        font-size: 18px;
        font-weight: 500;
        margin-bottom: 20px;
    }

    .order-details {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }

    .order-detail-item {
        flex: 1;
        min-width: 120px;
    }

    .order-detail-item strong {
        display: block;
        color: #666666;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
        font-weight: 500;
    }

    .order-detail-item span {
        font-size: 14px;
        font-weight: 500;
        color: #333333;
    }

    .content-section {
        padding: 30px 20px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 500;
        color: #333333;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e5e5;
    }

    .product-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }

    .product-table th {
        background-color: #f8f8f8;
        padding: 15px 12px;
        text-align: left;
        font-weight: 500;
        color: #666666;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e5e5e5;
    }

    .product-table td {
        padding: 20px 12px;
        border-bottom: 1px solid #f0f0f0;
    }

    .product-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 15px;
    }

    .product-info {
        display: flex;
        align-items: center;
    }

    .product-details h4 {
        font-size: 14px;
        font-weight: 500;
        color: #333333;
        margin-bottom: 4px;
    }

    .product-details p {
        font-size: 12px;
        color: #888888;
    }

    .price-breakdown {
        background-color: #f8f8f8;
        padding: 20px;
        margin-top: 30px;
    }

    .price-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
        color: #333333;
    }

    .price-row.total {
        border-top: 1px solid #e5e5e5;
        margin-top: 15px;
        padding-top: 15px;
        font-weight: 500;
        font-size: 16px;
        color: #000000;
    }

    .shipping-address {
        background-color: #f8f8f8;
        border: 1px solid #e5e5e5;
        padding: 20px;
        margin-top: 20px;
    }

    .shipping-address h3 {
        color: #333333;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 15px;
    }

    .address-details {
        line-height: 1.5;
        color: #666666;
        font-size: 14px;
    }

    .cta-section {
        text-align: center;
        padding: 40px 20px;
        background-color: #f8f8f8;
        border-top: 1px solid #e5e5e5;
    }

    .cta-section h3 {
        font-size: 16px;
        font-weight: 500;
        color: #333333;
        margin-bottom: 10px;
    }

    .cta-section p {
        color: #666666;
        font-size: 14px;
    }

    .cta-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 25px;
    }

    .btn {
        display: inline-block !important;
        padding: 12px 24px;
        text-decoration: none !important;
        font-weight: 500;
        font-size: 14px;
        border: 1px solid transparent;
        text-align: center;
        transition: all 0.2s ease;
        mso-hide: all;
    }

    .btn-primary {
        background-color: #000000 !important;
        color: white !important;
        border-color: #000000 !important;
    }

    .btn-secondary {
        background-color: white !important;
        color: #333333 !important;
        border-color: #333333 !important;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        color: white;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .footer {
        background-color: #000000;
        color: #cccccc;
        padding: 40px 20px;
        text-align: center;
        font-size: 12px;
    }

    .footer h3 {
        color: white;
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 20px;
    }

    .footer p {
        margin-bottom: 20px;
    }

    .footer-links {
        margin: 25px 0;
    }

    .footer-links a {
        color: #cccccc;
        text-decoration: none;
        margin: 0 15px;
        font-size: 12px;
    }

    .footer-links a:hover {
        color: white;
    }

    .social-links {
        margin-top: 25px;
    }

    .social-links a {
        display: inline-block;
        margin: 0 10px;
        color: #cccccc;
        font-size: 16px;
        text-decoration: none;
    }

    /* Email-specific utility classes */
    .tracking-box {
        background-color: #e8f5e9;
        border: 1px solid #4caf50;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
        text-align: center;
    }

    .tracking-box h3 {
        color: #2e7d32;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .tracking-number {
        font-family: 'Courier New', monospace;
        font-size: 18px;
        font-weight: 600;
        color: #1b5e20;
        letter-spacing: 1px;
    }

    .delivery-info {
        background-color: #fff3e0;
        border-left: 4px solid #ff9800;
        padding: 15px 20px;
        margin: 20px 0;
    }

    .delivery-info p {
        margin: 0;
        color: #e65100;
        font-size: 14px;
    }

    .features-list {
        background-color: #f8f8f8;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
    }

    .features-list h2 {
        color: #333333;
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 15px;
    }

    .feature-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 12px;
        font-size: 14px;
        color: #555555;
    }

    .feature-icon {
        margin-right: 10px;
        font-size: 16px;
    }

    .help-section {
        background-color: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 20px;
        margin: 20px 0;
    }

    .help-section h2 {
        color: #1565c0;
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 10px;
    }

    .help-section p {
        color: #424242;
        font-size: 14px;
        margin-bottom: 5px;
    }

    @media screen and (max-width: 600px) {
        .email-container {
            margin: 0;
        }

        .email-header {
            padding: 30px 15px;
        }

        .email-header h1 {
            font-size: 20px;
        }

        .order-details {
            flex-direction: column;
            gap: 15px;
        }

        .content-section {
            padding: 20px 15px;
        }

        .product-table th:nth-child(3),
        .product-table td:nth-child(3) {
            display: none;
        }

        .cta-buttons {
            flex-direction: column;
            align-items: center;
        }

        .cta-buttons a {
            width: 100%;
            max-width: 200px;
            margin: 5px 0 !important;
            display: block !important;
        }

        .footer {
            padding: 30px 15px;
        }
    }
</style>
