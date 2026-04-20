<?php
/**
 * Landing Page - es21plus
 *
 * Página pública de presentación del sistema de inventario de motos.
 * Incluye secciones de héroe, funcionalidades, precios, testimonios y contacto.
 *
 * @package  Es21Plus
 * @author   Carlitos6712
 * @version  1.0.0
 */

$contactSuccess = false;
$contactError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    $nombre  = htmlspecialchars(trim($_POST['nombre']  ?? ''), ENT_QUOTES, 'UTF-8');
    $email   = filter_var(trim($_POST['email']  ?? ''), FILTER_SANITIZE_EMAIL);
    $asunto  = htmlspecialchars(trim($_POST['asunto']  ?? ''), ENT_QUOTES, 'UTF-8');
    $mensaje = htmlspecialchars(trim($_POST['mensaje'] ?? ''), ENT_QUOTES, 'UTF-8');

    if ($nombre && filter_var($email, FILTER_VALIDATE_EMAIL) && $asunto && $mensaje) {
        // TODO: Integrar envío de email (PHPMailer/SMTP)
        $contactSuccess = true;
    } else {
        $contactError = 'Por favor completa todos los campos correctamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="es21plus – Sistema de inventario inteligente para talleres, distribuidores y concesionarios de motos. Controla stock, movimientos y alertas en tiempo real.">
    <title>es21plus – Inventario Inteligente para Motos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/landing.css">
</head>
<body>

    <!-- =========================================================
         NAV
    ========================================================= -->
    <nav id="navbar" class="navbar">
        <div class="navbar__container">

            <!-- Logo -->
            <a href="landing.php" class="navbar__brand">
                <svg class="navbar__logo-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                </svg>
                <span class="navbar__brand-text">es21plus</span>
            </a>

            <!-- Center links -->
            <ul id="nav-menu" class="navbar__menu">
                <li><a href="#funcionalidades" class="navbar__link">Funcionalidades</a></li>
                <li><a href="#precios"          class="navbar__link">Precios</a></li>
                <li><a href="#contacto"         class="navbar__link">Contacto</a></li>
            </ul>

            <!-- Actions -->
            <div class="navbar__actions">
                <a href="login.php" class="btn-outline">Iniciar Sesión</a>
                <a href="login.php" class="btn-primary">Empezar Gratis</a>
            </div>

            <!-- Hamburger -->
            <button id="nav-toggle" class="navbar__hamburger" aria-label="Abrir menú" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

        </div>
    </nav>

    <!-- =========================================================
         HERO
    ========================================================= -->
    <section id="hero" class="hero">
        <div class="hero__container">
            <div class="hero__content">
                <span class="hero__badge">Sistema N°1 de Inventario para Motos</span>
                <h1 class="hero__title">
                    Gestiona tu inventario de motos con
                    <span class="highlight">precisión y velocidad</span>
                </h1>
                <p class="hero__description">
                    Controla stock, movimientos y alertas en tiempo real desde un panel centralizado.
                    Diseñado para talleres, distribuidores y concesionarios.
                </p>
                <div class="hero__ctas">
                    <a href="login.php"         class="btn-primary btn--lg">Empezar Gratis</a>
                    <a href="#funcionalidades"  class="btn-secondary btn--lg">Ver Funcionalidades</a>
                </div>
            </div>

            <!-- Dashboard mockup -->
            <div class="hero__mockup" aria-hidden="true">
                <div class="mockup-browser">
                    <div class="mockup-browser__bar">
                        <span class="mockup-browser__dot mockup-browser__dot--red"></span>
                        <span class="mockup-browser__dot mockup-browser__dot--yellow"></span>
                        <span class="mockup-browser__dot mockup-browser__dot--green"></span>
                        <div class="mockup-browser__url">es21plus.dev/dashboard</div>
                    </div>
                    <div class="mockup-browser__body">
                        <!-- Sidebar -->
                        <div class="mockup-sidebar">
                            <div class="mockup-sidebar__item mockup-sidebar__item--active"></div>
                            <div class="mockup-sidebar__item"></div>
                            <div class="mockup-sidebar__item"></div>
                            <div class="mockup-sidebar__item"></div>
                            <div class="mockup-sidebar__item"></div>
                        </div>
                        <!-- Content area -->
                        <div class="mockup-content">
                            <!-- Stats row -->
                            <div class="mockup-stats">
                                <div class="mockup-stat mockup-stat--orange"></div>
                                <div class="mockup-stat mockup-stat--slate"></div>
                                <div class="mockup-stat mockup-stat--dark"></div>
                            </div>
                            <!-- Chart area -->
                            <div class="mockup-chart">
                                <div class="mockup-chart__bars">
                                    <div class="mockup-chart__bar" style="height:40%"></div>
                                    <div class="mockup-chart__bar" style="height:65%"></div>
                                    <div class="mockup-chart__bar" style="height:50%"></div>
                                    <div class="mockup-chart__bar" style="height:80%"></div>
                                    <div class="mockup-chart__bar" style="height:60%"></div>
                                    <div class="mockup-chart__bar" style="height:90%"></div>
                                    <div class="mockup-chart__bar mockup-chart__bar--accent" style="height:75%"></div>
                                </div>
                            </div>
                            <!-- Table rows -->
                            <div class="mockup-table">
                                <div class="mockup-table__row"></div>
                                <div class="mockup-table__row mockup-table__row--alt"></div>
                                <div class="mockup-table__row"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- =========================================================
         STATS
    ========================================================= -->
    <section id="stats" class="stats">
        <div class="stats__container">

            <div class="stat-item">
                <span class="stat-item__value">500+</span>
                <span class="stat-item__label">Negocios activos</span>
            </div>

            <div class="stat-item">
                <span class="stat-item__value">50K+</span>
                <span class="stat-item__label">Productos gestionados</span>
            </div>

            <div class="stat-item">
                <span class="stat-item__value">99.9%</span>
                <span class="stat-item__label">Uptime</span>
            </div>

            <div class="stat-item">
                <span class="stat-item__value">24/7</span>
                <span class="stat-item__label">Soporte</span>
            </div>

        </div>
    </section>

    <!-- =========================================================
         FUNCIONALIDADES
    ========================================================= -->
    <section id="funcionalidades" class="features">
        <div class="features__container">
            <div class="section-header">
                <h2 class="section-header__title">Todo lo que necesitas para gestionar tu inventario</h2>
                <p class="section-header__subtitle">
                    es21plus reúne en un solo lugar las herramientas que tu negocio necesita
                    para operar con eficiencia y total trazabilidad.
                </p>
            </div>

            <div class="features__grid">

                <!-- 1. Inventario en Tiempo Real -->
                <div class="feature-card">
                    <div class="feature-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <h3 class="feature-card__title">Inventario en Tiempo Real</h3>
                    <p class="feature-card__description">Stock actualizado al instante con cada movimiento registrado. Sin retrasos, sin discrepancias.</p>
                </div>

                <!-- 2. Gestión de Categorías -->
                <div class="feature-card">
                    <div class="feature-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </div>
                    <h3 class="feature-card__title">Gestión de Categorías</h3>
                    <p class="feature-card__description">Organiza productos por marca, modelo, tipo y más. Estructura tu catálogo con total flexibilidad.</p>
                </div>

                <!-- 3. Historial Completo -->
                <div class="feature-card">
                    <div class="feature-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <h3 class="feature-card__title">Historial Completo</h3>
                    <p class="feature-card__description">Trazabilidad total de entradas y salidas de stock. Consulta cualquier movimiento en segundos.</p>
                </div>

                <!-- 4. Alertas Automáticas -->
                <div class="feature-card">
                    <div class="feature-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </div>
                    <h3 class="feature-card__title">Alertas Automáticas</h3>
                    <p class="feature-card__description">Notificaciones cuando el stock cae por debajo del mínimo. Nunca te quedes sin producto clave.</p>
                </div>

                <!-- 5. API RESTful -->
                <div class="feature-card">
                    <div class="feature-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="16 18 22 12 16 6"/>
                            <polyline points="8 6 2 12 8 18"/>
                        </svg>
                    </div>
                    <h3 class="feature-card__title">API RESTful</h3>
                    <p class="feature-card__description">Integra es21plus con tus sistemas existentes mediante nuestra API documentada y segura.</p>
                </div>

                <!-- 6. Acceso Multi-usuario -->
                <div class="feature-card">
                    <div class="feature-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <h3 class="feature-card__title">Acceso Multi-usuario</h3>
                    <p class="feature-card__description">Roles y permisos granulares para todo tu equipo. Controla quién puede ver y modificar qué.</p>
                </div>

            </div>
        </div>
    </section>

    <!-- =========================================================
         CÓMO FUNCIONA
    ========================================================= -->
    <section id="como-funciona" class="how-it-works">
        <div class="how-it-works__container">
            <div class="section-header">
                <h2 class="section-header__title">Empieza en minutos</h2>
                <p class="section-header__subtitle">
                    Sin instalaciones complejas, sin configuraciones interminables. Tres pasos y listo.
                </p>
            </div>

            <div class="steps">
                <div class="steps__connector" aria-hidden="true"></div>

                <div class="step-item">
                    <div class="step-item__number">01</div>
                    <h3 class="step-item__title">Crea tu cuenta</h3>
                    <p class="step-item__description">Regístrate en segundos, sin tarjeta de crédito. Tu primer mes es completamente gratis.</p>
                </div>

                <div class="step-item">
                    <div class="step-item__number">02</div>
                    <h3 class="step-item__title">Carga tu inventario</h3>
                    <p class="step-item__description">Agrega productos, categorías y stock inicial de forma manual o mediante importación CSV.</p>
                </div>

                <div class="step-item">
                    <div class="step-item__number">03</div>
                    <h3 class="step-item__title">Controla todo</h3>
                    <p class="step-item__description">Monitorea movimientos y recibe alertas en tiempo real desde tu panel centralizado.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================================================
         TESTIMONIOS
    ========================================================= -->
    <section id="testimonios" class="testimonials">
        <div class="testimonials__container">
            <div class="section-header">
                <h2 class="section-header__title">Lo que dicen nuestros clientes</h2>
                <p class="section-header__subtitle">
                    Miles de negocios ya confían en es21plus para gestionar su inventario.
                </p>
            </div>

            <div class="testimonials__grid">

                <!-- Testimonio 1 -->
                <div class="testimonial-card">
                    <div class="testimonial-card__stars" aria-label="5 estrellas">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="#f59e0b" stroke="none" aria-hidden="true">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <?php endfor; ?>
                    </div>
                    <blockquote class="testimonial-card__quote">
                        "Desde que implementamos es21plus, nuestro control de inventario mejoró un 80%. Los reportes son increíbles."
                    </blockquote>
                    <div class="testimonial-card__author">
                        <div class="testimonial-card__avatar testimonial-card__avatar--blue" aria-hidden="true">JP</div>
                        <div class="testimonial-card__info">
                            <span class="testimonial-card__name">Juan Pérez</span>
                            <span class="testimonial-card__role">Concesionario Honda Monterrey</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonio 2 -->
                <div class="testimonial-card">
                    <div class="testimonial-card__stars" aria-label="5 estrellas">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="#f59e0b" stroke="none" aria-hidden="true">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <?php endfor; ?>
                    </div>
                    <blockquote class="testimonial-card__quote">
                        "Nunca más perdimos una pieza por falta de stock. Las alertas automáticas son un salvavidas para nuestro taller."
                    </blockquote>
                    <div class="testimonial-card__author">
                        <div class="testimonial-card__avatar testimonial-card__avatar--green" aria-hidden="true">MG</div>
                        <div class="testimonial-card__info">
                            <span class="testimonial-card__name">María García</span>
                            <span class="testimonial-card__role">Taller BMW Motos CDMX</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonio 3 -->
                <div class="testimonial-card">
                    <div class="testimonial-card__stars" aria-label="5 estrellas">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="#f59e0b" stroke="none" aria-hidden="true">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <?php endfor; ?>
                    </div>
                    <blockquote class="testimonial-card__quote">
                        "La API nos permitió integrar con nuestro ERP en menos de un día. Soporte técnico excelente y muy rápido."
                    </blockquote>
                    <div class="testimonial-card__author">
                        <div class="testimonial-card__avatar testimonial-card__avatar--purple" aria-hidden="true">CL</div>
                        <div class="testimonial-card__info">
                            <span class="testimonial-card__name">Carlos López</span>
                            <span class="testimonial-card__role">Distribuidora Yamaha Norte</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- =========================================================
         PRECIOS
    ========================================================= -->
    <section id="precios" class="pricing">
        <div class="pricing__container">
            <div class="section-header">
                <h2 class="section-header__title">Planes simples y transparentes</h2>
                <p class="section-header__subtitle">
                    Sin costos ocultos. Escala cuando tu negocio lo necesite.
                </p>
            </div>

            <div class="pricing__grid">

                <!-- Plan Básico -->
                <div class="pricing-card">
                    <div class="pricing-card__header">
                        <h3 class="pricing-card__name">Básico</h3>
                        <div class="pricing-card__price">
                            <span class="pricing-card__amount">$0</span>
                            <span class="pricing-card__period">/mes</span>
                        </div>
                    </div>
                    <ul class="pricing-card__features">
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Hasta 100 productos
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            1 usuario
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Soporte por email
                        </li>
                        <li class="pricing-card__feature pricing-card__feature--disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#CBD5E1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Alertas avanzadas
                        </li>
                        <li class="pricing-card__feature pricing-card__feature--disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#CBD5E1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Acceso a API
                        </li>
                    </ul>
                    <a href="login.php" class="pricing-card__cta btn-outline">Empezar Gratis</a>
                </div>

                <!-- Plan Pro (destacado) -->
                <div class="pricing-card pricing-card--featured">
                    <div class="pricing-card__badge">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="#f59e0b" stroke="none" aria-hidden="true">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        Más popular
                    </div>
                    <div class="pricing-card__header">
                        <h3 class="pricing-card__name">Pro</h3>
                        <div class="pricing-card__price">
                            <span class="pricing-card__amount">$29</span>
                            <span class="pricing-card__period">/mes</span>
                        </div>
                    </div>
                    <ul class="pricing-card__features">
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Productos ilimitados
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            5 usuarios
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Alertas avanzadas
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Acceso a API
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Soporte prioritario
                        </li>
                    </ul>
                    <a href="login.php" class="pricing-card__cta btn-primary">Comenzar Prueba</a>
                </div>

                <!-- Plan Enterprise -->
                <div class="pricing-card">
                    <div class="pricing-card__header">
                        <h3 class="pricing-card__name">Enterprise</h3>
                        <div class="pricing-card__price">
                            <span class="pricing-card__amount pricing-card__amount--contact">Contactar</span>
                        </div>
                    </div>
                    <ul class="pricing-card__features">
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Usuarios ilimitados
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            SLA 99.9%
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Soporte dedicado
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Onboarding personalizado
                        </li>
                        <li class="pricing-card__feature">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#64748B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Integraciones custom
                        </li>
                    </ul>
                    <a href="#contacto" class="pricing-card__cta btn-outline">Contactar Ventas</a>
                </div>

            </div>
        </div>
    </section>

    <!-- =========================================================
         CONTACTO
    ========================================================= -->
    <section id="contacto" class="contact">
        <div class="contact__container">

            <!-- Left column: info -->
            <div class="contact__info">
                <h2 class="contact__title">Tienes preguntas?</h2>
                <p class="contact__subtitle">
                    Nuestro equipo está listo para ayudarte a encontrar el plan perfecto
                    para tu negocio. Escríbenos y te respondemos en menos de 24 horas.
                </p>

                <div class="contact__details">

                    <div class="contact-detail">
                        <div class="contact-detail__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <div class="contact-detail__text">
                            <span class="contact-detail__label">Email</span>
                            <a href="mailto:contacto@es21plus.dev" class="contact-detail__value">contacto@es21plus.dev</a>
                        </div>
                    </div>

                    <div class="contact-detail">
                        <div class="contact-detail__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </div>
                        <div class="contact-detail__text">
                            <span class="contact-detail__label">Teléfono</span>
                            <a href="tel:+34900123456" class="contact-detail__value">+34 900 123 456</a>
                        </div>
                    </div>

                    <div class="contact-detail">
                        <div class="contact-detail__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </div>
                        <div class="contact-detail__text">
                            <span class="contact-detail__label">Dirección</span>
                            <span class="contact-detail__value">Barcelona, España</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right column: form -->
            <div class="contact__form-wrapper">

                <?php if ($contactSuccess): ?>
                <div class="form-alert form-alert--success" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    Mensaje enviado con éxito. Te contactaremos pronto.
                </div>
                <?php endif; ?>

                <?php if ($contactError !== ''): ?>
                <div class="form-alert form-alert--error" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= htmlspecialchars($contactError, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="#contacto" class="contact-form" novalidate>
                    <input type="hidden" name="contact_form" value="1">

                    <div class="form-group">
                        <label for="nombre" class="form-group__label">Nombre completo</label>
                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            class="form-group__input"
                            placeholder="Tu nombre"
                            required
                            maxlength="100"
                            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-group__label">Correo electrónico</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-group__input"
                            placeholder="tu@email.com"
                            required
                            maxlength="150"
                            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="asunto" class="form-group__label">Asunto</label>
                        <input
                            type="text"
                            id="asunto"
                            name="asunto"
                            class="form-group__input"
                            placeholder="¿En qué podemos ayudarte?"
                            required
                            maxlength="200"
                            value="<?= htmlspecialchars($_POST['asunto'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="mensaje" class="form-group__label">Mensaje</label>
                        <textarea
                            id="mensaje"
                            name="mensaje"
                            class="form-group__input form-group__textarea"
                            rows="4"
                            placeholder="Cuéntanos más sobre tu consulta..."
                            required
                            maxlength="2000"
                        ><?= htmlspecialchars($_POST['mensaje'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <button type="submit" class="btn-primary btn--full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Enviar mensaje
                    </button>
                </form>
            </div>

        </div>
    </section>

    <!-- =========================================================
         FOOTER
    ========================================================= -->
    <footer class="footer">
        <div class="footer__container">

            <!-- Top row -->
            <div class="footer__top">

                <!-- Brand + tagline -->
                <div class="footer__brand">
                    <a href="landing.php" class="navbar__brand">
                        <svg class="navbar__logo-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                        </svg>
                        <span class="navbar__brand-text">es21plus</span>
                    </a>
                    <p class="footer__tagline">El sistema de inventario inteligente para la industria de las motos.</p>
                </div>

                <!-- Link columns -->
                <div class="footer__links">

                    <div class="footer__col">
                        <h4 class="footer__col-title">Producto</h4>
                        <ul class="footer__col-list">
                            <li><a href="#funcionalidades" class="footer__link">Funcionalidades</a></li>
                            <li><a href="#precios"         class="footer__link">Precios</a></li>
                            <li><a href="#"               class="footer__link">API</a></li>
                        </ul>
                    </div>

                    <div class="footer__col">
                        <h4 class="footer__col-title">Empresa</h4>
                        <ul class="footer__col-list">
                            <li><a href="#" class="footer__link">Nosotros</a></li>
                            <li><a href="#" class="footer__link">Blog</a></li>
                            <li><a href="#contacto" class="footer__link">Contacto</a></li>
                        </ul>
                    </div>

                    <div class="footer__col">
                        <h4 class="footer__col-title">Legal</h4>
                        <ul class="footer__col-list">
                            <li><a href="#" class="footer__link">Privacidad</a></li>
                            <li><a href="#" class="footer__link">Términos</a></li>
                        </ul>
                    </div>

                </div>
            </div>

            <!-- Bottom row -->
            <div class="footer__bottom">
                <p class="footer__copyright">
                    &copy; 2024 es21plus. Todos los derechos reservados.
                </p>
                <div class="footer__socials">

                    <!-- Twitter / X -->
                    <a href="#" class="footer__social-link" aria-label="Twitter / X">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                    </a>

                    <!-- LinkedIn -->
                    <a href="#" class="footer__social-link" aria-label="LinkedIn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                            <rect x="2" y="9" width="4" height="12"/>
                            <circle cx="4" cy="4" r="2"/>
                        </svg>
                    </a>

                    <!-- GitHub -->
                    <a href="#" class="footer__social-link" aria-label="GitHub">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>
                        </svg>
                    </a>

                </div>
            </div>

        </div>
    </footer>

    <script src="js/landing.js" defer></script>
</body>
</html>
