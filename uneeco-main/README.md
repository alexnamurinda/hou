# Uneeco Uganda Limited — Website

Official website for **Uneeco Uganda Limited**, one of East Africa's leading suppliers to the print and packaging industry. Built with Bootstrap 5, custom CSS, and vanilla JavaScript.

---

## Project Structure

```
uneeco/
├── index.html                      # Homepage
├── send-mail.php                   # Contact form mail handler (backend)
├── pages/
│   ├── products.html               # Full product catalogue
│   ├── about.html                  # Company story, mission & values
│   ├── contact.html                # Contact form, map & FAQ
│   └── search.html                 # Search results page
├── includes/
│   ├── navbar.html                 # Navbar component reference
│   └── footer.html                 # Footer component reference
└── assets/
    ├── css/
    │   ├── styles.css              # Global styles & brand variables
    │   ├── responsive.css          # Breakpoint & mobile overrides
    │   ├── about.css               # About page styles
    │   ├── contact.css             # Contact page styles
    │   └── products.css            # Products page styles
    ├── js/
    │   ├── animations.js           # AOS initialization
    │   ├── navbar.js               # Sticky navbar & mobile sidebar
    │   ├── scroll.js               # Back-to-top button
    │   └── contact.js              # Contact form validation & submission
    └── images/                     # Logos, product & partner images
```

---

## Pages

| Page | Path | Description |
|------|------|-------------|
| Home | `index.html` | Hero, product highlights, Why Us stats, partner showcase |
| Products | `pages/products.html` | Full catalogue — paper, inks, plates, sundries, large format |
| About | `pages/about.html` | Company story (est. 1984), mission, vision, values |
| Contact | `pages/contact.html` | Contact form, Google Maps embed, business hours, FAQ |

---

## Features

- Fully responsive — mobile, tablet, desktop
- Sticky navbar with scroll-triggered styling
- Mobile slide-in sidebar navigation
- Floating chat widget (WhatsApp, email, call)
- AOS scroll-triggered animations
- Contact form with Google reCAPTCHA v2 and PHP mail handler (`send-mail.php`)
- Google Maps embed on contact page
- FAQ accordion with show/hide toggle
- Back-to-top button
- SEO meta descriptions on all pages
- Favicon linked via brand logo

---

## Technologies

| Library / Tool | Version | Purpose |
|----------------|---------|---------|
| Bootstrap | 5.3.0 | Grid, components |
| Font Awesome | 6.4.0 | Icons |
| AOS | 2.3.4 | Scroll animations |
| Google Fonts | — | Playfair Display, DM Sans |
| Google reCAPTCHA | v2 | Contact form spam protection |
| Vanilla JavaScript | — | All interactivity |
| PHP | — | Contact form mail handler |

---

## Local Development

Requires a local server (XAMPP, Laragon, etc.) because of the PHP mail handler.

1. Place the `uneeco/` folder inside your server's `htdocs` (XAMPP) or `www` directory
2. Start Apache in your server control panel
3. Open `http://localhost/uneeco/` in your browser

---

## Customisation

### Brand Colors
Edit CSS variables at the top of `assets/css/styles.css`:
```css
:root {
    --primary:        #1a3d2b;
    --primary-mid:    #2c6644;
    --primary-light:  #4a9e68;
    --accent:         #b08d57;
}
```

### Adding a New Page
1. Duplicate the closest existing page (e.g. `contact.html`)
2. Update the `<title>` and `<meta name="description">`
3. Set the correct `active` class on the navbar link
4. Update `href` paths — pages inside `pages/` reference assets as `../assets/`

### Contact Form
The form posts to `send-mail.php`. Update the recipient address at the top of that file to change where enquiries are delivered.

---

## Deployment

1. Upload the entire `uneeco/` folder to your web host via FTP or cPanel File Manager
2. Ensure the server supports PHP (required for `send-mail.php`)
3. Confirm the `send-mail.php` recipient email is correct before going live
4. Test the contact form end-to-end after deployment

---

## Browser Support

Chrome, Firefox, Safari, Edge (latest versions) · iOS Safari · Android Chrome

---

**Version:** 2.0
**Last Updated:** 2025
**Status:** Production Ready
