// Form Component Behaviors
class Form {
  constructor(element) {
    this.element = element;
    this.init();
  }

  init() {
    this.setupValidation();
    this.setupSubmission();
  }

  setupValidation() {
    this.element.addEventListener('submit', (e) => {
      e.preventDefault();
      if (this.validate()) {
        this.submit();
      }
    });

    // Real-time validation
    const inputs = this.element.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      input.addEventListener('input', () => {
        this.clearFieldError(input);
      });

      input.addEventListener('blur', () => {
        this.validateField(input);
      });
    });
  }

  setupSubmission() {
    // Form submission will be handled by specific page implementations
    // This provides the basic structure
  }

  validate() {
    let isValid = true;
    const inputs = this.element.querySelectorAll('input[required], select[required], textarea[required]');

    inputs.forEach(input => {
      if (!this.validateField(input)) {
        isValid = false;
      }
    });

    return isValid;
  }

  validateField(input) {
    const value = input.value.trim();
    const isRequired = input.hasAttribute('required');
    let isValid = true;

    if (isRequired && !value) {
      this.setFieldError(input, 'This field is required');
      isValid = false;
    } else if (input.type === 'email' && value && !this.isValidEmail(value)) {
      this.setFieldError(input, 'Please enter a valid email address');
      isValid = false;
    } else if (input.type === 'password' && value && value.length < 8) {
      this.setFieldError(input, 'Password must be at least 8 characters');
      isValid = false;
    } else if (input.hasAttribute('minlength') && value.length < parseInt(input.getAttribute('minlength'))) {
      this.setFieldError(input, `Please enter at least ${input.getAttribute('minlength')} characters`);
      isValid = false;
    } else if (input.hasAttribute('maxlength') && value.length > parseInt(input.getAttribute('maxlength'))) {
      this.setFieldError(input, `Please enter no more than ${input.getAttribute('maxlength')} characters`);
      isValid = false;
    } else {
      this.clearFieldError(input);
    }

    return isValid;
  }

  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  setFieldError(input, message) {
    const formGroup = input.closest('.form-group');
    if (formGroup) {
      // Remove existing error
      this.clearFieldError(input);

      // Add error state
      formGroup.classList.add('error');

      // Add error message
      const errorMessage = document.createElement('div');
      errorMessage.className = 'form-help-text error';
      errorMessage.textContent = message;
      formGroup.appendChild(errorMessage);
    }
  }

  clearFieldError(input) {
    const formGroup = input.closest('.form-group');
    if (formGroup) {
      formGroup.classList.remove('error');

      const errorMessage = formGroup.querySelector('.form-help-text.error');
      if (errorMessage) {
        errorMessage.remove();
      }
    }
  }

  submit() {
    // Show loading state
    this.element.classList.add('form-loading');

    // Collect form data
    const formData = new FormData(this.element);
    const data = Object.fromEntries(formData.entries());

    // This would be implemented by specific page controllers
    // For now, just simulate submission
    setTimeout(() => {
      this.element.classList.remove('form-loading');
      this.showSuccessMessage('Form submitted successfully!');

      // Reset form
      this.element.reset();
    }, 1500);
  }

  showSuccessMessage(message) {
    // Remove existing messages
    const existingSuccess = this.element.querySelector('.form-success-alert');
    if (existingSuccess) {
      existingSuccess.remove();
    }

    const successAlert = document.createElement('div');
    successAlert.className = 'form-success-alert';
    successAlert.textContent = message;

    // Insert after form header or at beginning
    const formHeader = this.element.querySelector('.form-header');
    if (formHeader) {
      formHeader.insertAdjacentElement('afterend', successAlert);
    } else {
      this.element.insertAdjacentElement('afterbegin', successAlert);
    }

    // Remove after 5 seconds
    setTimeout(() => {
      if (successAlert.parentElement) {
        successAlert.remove();
      }
    }, 5000);
  }

  showErrorMessage(message) {
    // Remove existing messages
    const existingError = this.element.querySelector('.form-error-alert');
    if (existingError) {
      existingError.remove();
    }

    const errorAlert = document.createElement('div');
    errorAlert.className = 'form-error-alert';
    errorAlert.textContent = message;

    // Insert after form header or at beginning
    const formHeader = this.element.querySelector('.form-header');
    if (formHeader) {
      formHeader.insertAdjacentElement('afterend', errorAlert);
    } else {
      this.element.insertAdjacentElement('afterbegin', errorAlert);
    }

    // Remove after 5 seconds
    setTimeout(() => {
      if (errorAlert.parentElement) {
        errorAlert.remove();
      }
    }, 5000);
  }
}

// Auto-initialize forms
document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('.form[data-auto-init]');
  forms.forEach(form => new Form(form));
});