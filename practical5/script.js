const loginForm = document.getElementById("loginForm");
const enrollmentInput = document.getElementById("enrollment");
const passwordInput = document.getElementById("password");
const togglePassword = document.getElementById("togglePassword");
const nameError = document.getElementById("nameError");
const passwordError = document.getElementById("passwordError");

if (loginForm) {
    loginForm.addEventListener("submit", function (event) {
        event.preventDefault();

        const enrollmentPattern = /^[A-Za-z0-9]+$/;
        const passwordPattern = /^[0-9]{6}$/;
        const enrollmentValue = enrollmentInput.value.trim();
        const passwordValue = passwordInput.value.trim();

        if (!enrollmentPattern.test(enrollmentValue)) {
            nameError.style.color = "red";
            nameError.textContent = "Invalid enrollment number!";
            return false;
        }

        if (!passwordPattern.test(passwordValue)) {
            passwordError.style.color = "red";
            passwordError.textContent = "Invalid password!";
            return false;
        }

        alert("Login successful!");
        window.location.href = "dashboard.html";
    });
}

if (togglePassword) {
    togglePassword.addEventListener("click", function () {
        const shouldShowPassword = passwordInput.type === "password";
        passwordInput.type = shouldShowPassword ? "text" : "password";
        togglePassword.textContent = shouldShowPassword ? "Hide Password" : "Show Password";
    });
}

document.querySelectorAll(".faq-question").forEach(button => {
  button.addEventListener("click", () => {
    const answer = button.nextElementSibling;
    answer.style.display = answer.style.display === "block" ? "none" : "block";
  });
});

const themeToggle = document.getElementById("themeToggle");

function applyTheme(theme) {
    document.body.classList.toggle("dark", theme === "dark");
    if (themeToggle) {
        themeToggle.textContent = theme === "dark" ? "Light Mode" : "Dark Mode";
        themeToggle.setAttribute("aria-label", theme === "dark" ? "Switch to light mode" : "Switch to dark mode");
    }
}

const savedTheme = localStorage.getItem("theme") || "light";
applyTheme(savedTheme);

if (themeToggle) {
    themeToggle.addEventListener("click", () => {
        const nextTheme = document.body.classList.contains("dark") ? "light" : "dark";
        localStorage.setItem("theme", nextTheme);
        applyTheme(nextTheme);
    });
}

const slides = document.querySelectorAll(".slide");
let slideIndex = 0;

if (slides.length > 0) {
    setInterval(() => {
        slides[slideIndex].classList.remove("active");
        slideIndex = (slideIndex + 1) % slides.length;
        slides[slideIndex].classList.add("active");
    }, 2500);
}

const hamburger = document.getElementById("hamburger");
const nav = document.getElementById("mainNav");

hamburger.addEventListener("click", () => {
  nav.classList.toggle("show");
});