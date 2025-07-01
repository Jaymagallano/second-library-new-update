/**
 * Gmail validation with visual feedback
 * This script validates Gmail addresses and shows visual indicators
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find all email input fields
    const emailInputs = document.querySelectorAll('input[type="email"]');

    emailInputs.forEach(input => {
        // Add input event listener for real-time validation
        input.addEventListener('input', function() {
            validateGmailAddress(this);
        });

        // Add blur event for final validation
        input.addEventListener('blur', function() {
            validateGmailAddress(this, true);
        });
    });

    /**
     * Validates if the input contains a valid Gmail address
     * @param {HTMLElement} input - The email input element
     * @param {boolean} showMessage - Whether to show validation message
     */
    function validateGmailAddress(input, showMessage = false) {
        const value = input.value.trim();

        // Clear previous validation state
        input.classList.remove('is-invalid', 'is-valid', 'gmail-valid');
        input.style.borderColor = '';
        input.style.backgroundColor = '';

        // Remove any existing feedback elements
        const parent = input.parentElement;
        const existingFeedback = parent.querySelector('.gmail-feedback');
        if (existingFeedback) {
            parent.removeChild(existingFeedback);
        }

        // Skip validation if empty
        if (value === '') return;

        // Check if it's a valid Gmail address
        if (isValidGmail(value)) {
            // Valid Gmail - show GREEN styling
            input.classList.add('gmail-valid');
            input.style.borderColor = '#28a745';
            input.style.backgroundColor = '#f8fff9';

            if (showMessage) {
                showGmailSuccess(input);
            }
        } else {
            // Any incorrect email (invalid format, typos, non-Gmail) - show RED styling
            input.classList.add('is-invalid');
            input.style.borderColor = '#dc3545';
            input.style.backgroundColor = '#fff5f5';

            if (showMessage) {
                // Check if it looks like a Gmail typo
                if (isGmailTypo(value)) {
                    showGmailError(input, 'Gmail typo detected. Check your spelling.');
                } else if (isValidEmail(value)) {
                    showGmailError(input, 'Please use a Gmail address.');
                } else {
                    showGmailError(input, 'Please enter a valid Gmail address.');
                }
            }
        }
    }

    /**
     * Checks if the provided string is a valid Gmail address
     * @param {string} email - The email to validate
     * @return {boolean} - Whether the email is a valid Gmail
     */
    function isValidGmail(email) {
        if (!email) return false;

        // Check for valid email format first
        if (!isValidEmail(email)) return false;

        // Check if it's a Gmail address (gmail.com or googlemail.com)
        const gmailPattern = /^[a-zA-Z0-9._%+-]+@(gmail\.com|googlemail\.com)$/i;
        return gmailPattern.test(email);
    }

    /**
     * Checks if the email looks like a Gmail typo
     * @param {string} email - The email to check
     * @return {boolean} - Whether it looks like a Gmail typo
     */
    function isGmailTypo(email) {
        if (!email || !email.includes('@')) return false;

        const domain = email.split('@')[1];
        if (!domain) return false;

        // Common Gmail typos
        const gmailTypos = [
            'gmai.com', 'gmial.com', 'gmail.co', 'gmail.cm', 'gmail.om',
            'gmaill.com', 'gmall.com', 'gmail.con', 'gmail.cmo',
            'gmailcom', 'gmail.', 'gmail', 'gmai.l.com', 'g.mail.com',
            'gmail.comm', 'gmail.coom', 'gmail.c0m', 'gm4il.com',
            'gmai1.com', 'gmaiI.com', 'gmail.c9m', 'gmail.xom',
            'gmail.vom', 'gmail.dom', 'gmail.fom', 'gmail.som',
            'googlemail.co', 'googlemail.cm', 'googlemial.com',
            'googemail.com', 'googlemai.com', 'googlemall.com'
        ];

        return gmailTypos.includes(domain.toLowerCase());
    }

    /**
     * Checks if the provided string is a valid email address
     * @param {string} email - The email to validate
     * @return {boolean} - Whether the email is valid
     */
    function isValidEmail(email) {
        if (!email) return false;
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailPattern.test(email);
    }

    /**
     * Shows a success message for valid Gmail
     * @param {HTMLElement} input - The input element
     */
    function showGmailSuccess(input) {
        const feedback = document.createElement('div');
        feedback.className = 'gmail-feedback gmail-success';
        feedback.innerHTML = '<i class="fas fa-check-circle"></i> Valid Gmail address';
        feedback.style.cssText = `
            color: #28a745;
            font-size: 11px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-family: 'Times New Roman', serif;
            font-style: italic;
        `;
        input.parentElement.appendChild(feedback);
    }

    /**
     * Shows an error message for invalid emails or Gmail typos
     * @param {HTMLElement} input - The input element
     * @param {string} message - The error message
     */
    function showGmailError(input, message) {
        const feedback = document.createElement('div');
        feedback.className = 'gmail-feedback gmail-error';
        feedback.innerHTML = `<i class="fas fa-times-circle"></i> ${message}`;
        feedback.style.cssText = `
            color: #dc3545;
            font-size: 11px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-family: 'Times New Roman', serif;
            font-style: italic;
        `;
        input.parentElement.appendChild(feedback);
    }
});