// ---------- Digital Library: client-side JavaScript ----------

document.addEventListener("DOMContentLoaded", function () {

  // Basic client-side validation for Register form
  const registerForm = document.getElementById("register-form");
  if (registerForm) {
    registerForm.addEventListener("submit", function (e) {
      const password = document.getElementById("password").value;
      const confirm = document.getElementById("confirm_password").value;
      if (password.length < 6) {
        alert("Password must be at least 6 characters long.");
        e.preventDefault();
        return;
      }
      if (password !== confirm) {
        alert("Passwords do not match.");
        e.preventDefault();
      }
    });
  }

  // Basic client-side validation for Login form
  const loginForm = document.getElementById("login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      const username = document.getElementById("username").value.trim();
      const password = document.getElementById("password").value.trim();
      if (!username || !password) {
        alert("Please enter both username and password.");
        e.preventDefault();
      }
    });
  }

  // Basic client-side validation for Contact form
  const contactForm = document.getElementById("contact-form");
  if (contactForm) {
    contactForm.addEventListener("submit", function (e) {
      const email = document.getElementById("email").value.trim();
      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailPattern.test(email)) {
        alert("Please enter a valid email address.");
        e.preventDefault();
      }
    });
  }

  // Basic client-side validation for the Submit Item form
  const submitForm = document.getElementById("submit-form");
  if (submitForm) {
    submitForm.addEventListener("submit", function (e) {
      const title = document.getElementById("title").value.trim();
      const collection = document.getElementById("collection_id").value;
      if (!title || !collection) {
        alert("Please provide a title and select a collection.");
        e.preventDefault();
      }
    });
  }

});
