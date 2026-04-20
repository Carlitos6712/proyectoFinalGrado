/**
 * Landing Page – Interacciones JS
 *
 * Handles navbar scroll effect, mobile menu toggle, smooth scroll,
 * active section highlight, contact form validation, and scroll animations.
 *
 * @author   Carlitos6712
 * @version  1.0.0
 * @module   landing
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  // ─────────────────────────────────────────────────────────────
  // 1. NAVBAR SCROLL EFFECT
  // ─────────────────────────────────────────────────────────────

  /**
   * Adds or removes the `navbar--scrolled` class on `#navbar`
   * based on the current vertical scroll position.
   *
   * @returns {void}
   */
  const handleNavbarScroll = () => {
    const navbar = document.getElementById('navbar');
    if (!navbar) return;

    if (window.scrollY > 20) {
      navbar.classList.add('navbar--scrolled');
    } else {
      navbar.classList.remove('navbar--scrolled');
    }
  };

  window.addEventListener('scroll', handleNavbarScroll, { passive: true });
  // Run once on load in case page is already scrolled
  handleNavbarScroll();


  // ─────────────────────────────────────────────────────────────
  // 2. MOBILE MENU TOGGLE
  // ─────────────────────────────────────────────────────────────

  const navToggle = document.getElementById('nav-toggle');
  const navMenu   = document.getElementById('nav-menu');

  /**
   * Opens or closes the mobile navigation menu and syncs
   * the `aria-expanded` attribute on the toggle button.
   *
   * @param {boolean} [force] - When provided, forces open (true) or closed (false).
   * @returns {void}
   */
  const toggleMobileMenu = (force) => {
    if (!navMenu || !navToggle) return;

    const isOpen = typeof force === 'boolean'
      ? force
      : !navMenu.classList.contains('is-open');

    navMenu.classList.toggle('is-open', isOpen);
    navToggle.setAttribute('aria-expanded', String(isOpen));
  };

  if (navToggle) {
    navToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleMobileMenu();
    });
  }

  // Close menu when a nav link is clicked
  if (navMenu) {
    navMenu.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => toggleMobileMenu(false));
    });
  }

  // Close menu on outside click
  document.addEventListener('click', (e) => {
    if (!navMenu || !navToggle) return;
    if (navMenu.classList.contains('is-open') &&
        !navMenu.contains(e.target) &&
        !navToggle.contains(e.target)) {
      toggleMobileMenu(false);
    }
  });


  // ─────────────────────────────────────────────────────────────
  // 3. SMOOTH SCROLL FOR ANCHOR LINKS
  // ─────────────────────────────────────────────────────────────

  /** Fixed navbar height offset (px) used to avoid content being hidden. */
  const NAVBAR_OFFSET = 80;

  /**
   * Smoothly scrolls to the element referenced by an anchor href,
   * accounting for the fixed navbar offset.
   *
   * @param {MouseEvent} e - The click event fired on an anchor element.
   * @returns {void}
   */
  const handleSmoothScroll = (e) => {
    const href = e.currentTarget.getAttribute('href');
    if (!href || href === '#') return;

    const target = document.querySelector(href);
    if (!target) return;

    e.preventDefault();

    const top = target.getBoundingClientRect().top + window.scrollY - NAVBAR_OFFSET;

    window.scrollTo({ top, behavior: 'smooth' });
  };

  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', handleSmoothScroll);
  });


  // ─────────────────────────────────────────────────────────────
  // 4. ACTIVE SECTION HIGHLIGHT IN NAVBAR
  // ─────────────────────────────────────────────────────────────

  /** IDs of all sections tracked for active-link highlighting. */
  const SECTION_IDS = [
    'hero',
    'funcionalidades',
    'como-funciona',
    'testimonios',
    'precios',
    'contacto',
  ];

  /**
   * Marks the navbar link that corresponds to `sectionId` as active
   * and removes the `active` class from every other nav link.
   *
   * @param {string} sectionId - The ID of the section now in viewport.
   * @returns {void}
   */
  const setActiveNavLink = (sectionId) => {
    document.querySelectorAll('.navbar__menu a').forEach((link) => {
      const matches = link.getAttribute('href') === `#${sectionId}`;
      link.classList.toggle('active', matches);
    });
  };

  const sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          setActiveNavLink(entry.target.id);
        }
      });
    },
    { threshold: 0.5 }
  );

  SECTION_IDS.forEach((id) => {
    const section = document.getElementById(id);
    if (section) sectionObserver.observe(section);
  });


  // ─────────────────────────────────────────────────────────────
  // 5. CONTACT FORM VALIDATION (CLIENT-SIDE)
  // ─────────────────────────────────────────────────────────────

  /** Regex for basic e-mail format validation. */
  const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  /**
   * Validation rules map: field name → validator function.
   *
   * @type {Object.<string, function(string): boolean>}
   */
  const VALIDATORS = {
    nombre:  (v) => v.trim().length >= 2,
    email:   (v) => EMAIL_REGEX.test(v.trim()),
    asunto:  (v) => v.trim().length >= 3,
    mensaje: (v) => v.trim().length >= 10,
  };

  /**
   * Marks a form field's parent `.form-group` as invalid by adding
   * the `.is-invalid` class.
   *
   * @param {HTMLElement} field - The input/textarea element to invalidate.
   * @returns {void}
   */
  const markInvalid = (field) => {
    const group = field.closest('.form-group');
    if (group) group.classList.add('is-invalid');
  };

  /**
   * Removes the `.is-invalid` class from a field's parent `.form-group`
   * when the field value satisfies its validation rule.
   *
   * @param {HTMLElement} field - The input/textarea element to validate.
   * @returns {void}
   */
  const clearInvalidIfValid = (field) => {
    const validate = VALIDATORS[field.name];
    if (!validate) return;

    if (validate(field.value)) {
      const group = field.closest('.form-group');
      if (group) group.classList.remove('is-invalid');
    }
  };

  /**
   * Validates every tracked field in the contact form.
   * Returns `true` when all fields pass; marks invalid ones otherwise.
   *
   * @param {HTMLFormElement} form - The form element to validate.
   * @returns {boolean} Whether the form is valid.
   */
  const validateContactForm = (form) => {
    let isValid = true;

    Object.keys(VALIDATORS).forEach((name) => {
      const field = form.elements[name];
      if (!field) return;

      if (!VALIDATORS[name](field.value)) {
        markInvalid(field);
        isValid = false;
      }
    });

    return isValid;
  };

  const contactForm = document.querySelector('.contact-form');

  if (contactForm) {
    // Real-time feedback: remove error once the user fixes the field
    Object.keys(VALIDATORS).forEach((name) => {
      const field = contactForm.elements[name];
      if (!field) return;

      field.addEventListener('input', () => clearInvalidIfValid(field));
    });

    // Submit handler: validate before allowing PHP to process the form
    contactForm.addEventListener('submit', (e) => {
      const isValid = validateContactForm(contactForm);
      if (!isValid) {
        e.preventDefault();
        // Focus the first invalid field for accessibility
        const firstInvalid = contactForm.querySelector('.form-group.is-invalid input, .form-group.is-invalid textarea');
        if (firstInvalid) firstInvalid.focus();
      }
      // If valid, the browser proceeds with the normal POST submit
    });
  }


  // ─────────────────────────────────────────────────────────────
  // 6. ANIMATE ELEMENTS ON SCROLL
  // ─────────────────────────────────────────────────────────────

  /** Whether the user has requested reduced motion via OS/browser settings. */
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Initialises scroll-based entrance animations for all `.fade-in-up` elements.
   *
   * When `prefers-reduced-motion` is active, all elements are shown immediately
   * without any transition. Otherwise, an `IntersectionObserver` adds the
   * `animate-in` class when each element enters the viewport.
   *
   * @returns {void}
   */
  const initScrollAnimations = () => {
    const animatables = document.querySelectorAll('.fade-in-up');
    if (!animatables.length) return;

    if (prefersReducedMotion) {
      // Respect user preference – reveal everything immediately
      animatables.forEach((el) => el.classList.add('animate-in'));
      return;
    }

    const animationObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('animate-in');
            observer.unobserve(entry.target); // Animate once
          }
        });
      },
      { threshold: 0.15 }
    );

    animatables.forEach((el) => animationObserver.observe(el));
  };

  initScrollAnimations();

});
