# Premium Classifieds - Settings Page Setup Guide

## 📋 Installation Instructions

### 1. File Placement
- Place `class-settings.php` in `includes/admin/`
- Place `admin.css` in `assets/css/`

### 2. Integration with Main Class

The Settings class is already initialized in `class-premium-classifieds.php`:

```php
if (is_admin()) {
    $this->components['settings'] = new PC_Settings();
}
```

### 3. Register Admin Menu

Already configured in `class-premium-classifieds.php` → `register_admin_menu()`:

```php
add_submenu_page(
    'premium-classifieds',
    __('Settings', 'premium-classifieds'),
    __('Settings', 'premium-classifieds'),
    'manage_options',
    'premium-classifieds-settings',
    [$this->components['settings'], 'render']
);
```

### 4. Enqueue Admin CSS

Already configured in `class-premium-classifieds.php` → `enqueue_admin_assets()`:

```php
wp_enqueue_style(
    'pc-admin-styles',
    PC_ASSETS_URL . 'css/admin.css',
    [],
    $this->version
);
```

---

## 🎛️ Settings Page Features

### Tab 1: Stripe & Payments
- **Mode Selector**: Test / Live mode switch
- **Test Keys**: Publishable and Secret keys for testing
- **Live Keys**: Production API keys
- **Webhook Configuration**: 
  - Auto-generated webhook URL
  - Copy button for easy setup
  - Webhook signing secret field
- **Connection Test**: Visual indicator if Stripe is configured correctly

### Tab 2: Pricing
- **Currency Selector**: USD, EUR, GBP, CAD, AUD, JPY
- **Contact Reveal Price**: $5.00 (default, editable)
- **Listing Boost Price**: $10.00 (default, editable)
- **Subscription Price**: $49.00 (default, editable)
- **Live Preview**: Cards showing current pricing in real-time

### Tab 3: Features
- **Messaging System**: Enable/disable internal messaging
- **Favorites**: Allow users to save listings
- **Subscriptions**: Enable premium monthly plans (coming soon)
- **Moderation**: Require admin approval before publishing
- **Listing Duration**: Auto-expire after X days (default: 30)
- **Max Images**: Maximum images per listing (default: 10)

### Tab 4: Notifications
- **Admin Email**: Custom admin notification email
- **New Listing Alert**: Notify admin when listing submitted
- **New Message Alert**: Notify sellers about buyer messages
- **Payment Alert**: Notify sellers when access purchased

### Tab 5: General
- **Listings Per Page**: Archive page pagination (default: 12)
- **reCAPTCHA**: Enable spam protection (optional)
- **System Information**: Plugin version, WP version, PHP version, database tables status

---

## 🔧 Configuration Steps

### Step 1: Configure Stripe (Required for payments)

1. Go to **Classifieds → Settings → Stripe & Payments**
2. Choose **Test Mode** for development
3. Get keys from [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
4. Add:
   - Publishable Key (Test): `pk_test_...`
   - Secret Key (Test): `sk_test_...`
5. Copy Webhook URL
6. In Stripe Dashboard → Webhooks → Add endpoint:
   - URL: `https://yoursite.com/pc-stripe-webhook`
   - Events: `checkout.session.completed`, `payment_intent.succeeded`, `charge.refunded`
7. Copy **Signing Secret** from Stripe and paste in settings
8. Click **Save Changes**
9. Verify "Stripe is configured correctly!" message appears

### Step 2: Set Pricing

1. Go to **Settings → Pricing**
2. Select your currency (default: USD)
3. Set prices:
   - Contact Reveal: $5.00 (one-time)
   - Listing Boost: $10.00 (30 days featured)
   - Subscription: $49.00/month (future)
4. Preview cards show live changes
5. Click **Save Changes**

### Step 3: Configure Features

1. Go to **Settings → Features**
2. Enable desired features:
   - ✅ Messaging System
   - ✅ Favorites
   - ✅ Require Approval (recommended)
3. Set listing duration (30 days)
4. Set max images (10)
5. Click **Save Changes**

### Step 4: Setup Notifications

1. Go to **Settings → Notifications**
2. Set admin email (defaults to WP admin email)
3. Enable notifications:
   - ✅ New Listing Submitted
   - ✅ New Message Received
   - ✅ Payment Received
4. Click **Save Changes**

### Step 5: General Settings

1. Go to **Settings → General**
2. Set listings per page (12)
3. (Optional) Enable reCAPTCHA if spam is an issue
4. Review System Information to ensure all database tables exist
5. Click **Save Changes**

---

## ✅ Verification Checklist

After configuration, verify:

- [ ] Stripe connection shows "configured correctly"
- [ ] Webhook URL is added to Stripe Dashboard
- [ ] Pricing preview cards show correct amounts
- [ ] Test payment works in frontend (use card: 4242 4242 4242 4242)
- [ ] Email notifications are being sent
- [ ] All database tables show ✓ in System Information

---

## 🚨 Troubleshooting

### Stripe Not Configured
**Problem**: Red error message saying "Stripe is not configured"  
**Solution**: 
- Check that API keys are correct (no extra spaces)
- Verify you're using the right mode (test vs live)
- Ensure keys match the selected mode

### Webhook Not Working
**Problem**: Payments complete but access not granted  
**Solution**:
- Verify webhook URL is added to Stripe Dashboard
- Check signing secret is correct
- Look at webhook logs in Stripe Dashboard for errors
- Enable WP_DEBUG to see error logs

### No Email Notifications
**Problem**: No emails being sent  
**Solution**:
- Check admin email is correct in Settings
- Verify notification checkboxes are enabled
- Test WordPress email (install WP Mail SMTP if needed)
- Check spam folder

### Settings Not Saving
**Problem**: Changes don't persist after clicking Save  
**Solution**:
- Check for JavaScript errors in browser console
- Verify user has `manage_options` capability
- Clear WordPress cache
- Check file permissions

---

## 🔐 Security Notes

- **API Keys**: Secret keys are stored in WordPress options (encrypted at rest by WP)
- **Webhook Secret**: Required to verify webhooks are from Stripe
- **Admin Only**: Settings page requires `manage_options` capability
- **Nonces**: All forms use WordPress nonces for CSRF protection

---

## 📚 Related Documentation

- [Stripe API Documentation](https://stripe.com/docs/api)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)
- [WordPress Settings API](https://developer.wordpress.org/plugins/settings/)

---

## 🆘 Support

For issues with the Settings Page:
1. Check this README
2. Review WordPress debug logs (`wp-content/debug.log`)
3. Check Stripe webhook logs in dashboard
4. Contact plugin support with error details
