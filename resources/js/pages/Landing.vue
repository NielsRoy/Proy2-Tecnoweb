<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { inicio, login, register } from '@/routes';

// URLs de las imágenes de la carpeta storage "landing/" (null si aún no se han subido → marcador).
defineProps<{
    imagenes: {
        hero: string | null;
        abarrotes: string | null;
        bebidas: string | null;
        limpieza: string | null;
        snacks: string | null;
        promo: string | null;
    };
}>();

const page = usePage();

// Requisito #7: contador de visitas en el pie (compartido por HandleInertiaRequests).
const visitas = computed<string>(() => {
    const value = page.props.visitasPagina;
    return typeof value === 'number' ? value.toLocaleString('es') : '—';
});

const autenticado = computed<boolean>(() => !!page.props.auth.user);

const anio = new Date().getFullYear();
</script>

<template>
    <Head title="Tienda D & D — Tu tienda de conveniencia">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <!-- Diseño FIJO: la clase raíz .landing fija fuente, tamaños (px) y colores; no reacciona a
         temas, tamaño de letra, alto contraste ni modo oscuro del resto del sistema. -->
    <div class="landing">
        <!-- Barra superior: marca + accesos (login / registro / ingresar al sistema) -->
        <header class="lp-nav">
            <div class="lp-container lp-nav__inner">
                <a href="#inicio" class="lp-brand">
                    <span class="lp-brand__mark">D&amp;D</span>
                    <span class="lp-brand__name">Tienda D &amp; D</span>
                </a>

                <nav class="lp-nav__links">
                    <template v-if="autenticado">
                        <Link :href="inicio()" class="lp-btn lp-btn--primary">
                            Ingresar al sistema
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="login()" class="lp-btn lp-btn--ghost">
                            Iniciar sesión
                        </Link>
                        <Link :href="register()" class="lp-btn lp-btn--primary">
                            Registrarse
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main id="inicio">
            <!-- HERO: mensaje principal + llamada a la acción -->
            <section class="lp-hero">
                <div class="lp-container lp-hero__grid">
                    <div class="lp-hero__copy">
                        <span class="lp-eyebrow">Tu tienda de conveniencia</span>
                        <h1 class="lp-hero__title">
                            Todo lo que necesitas,
                            <span class="lp-accent">a un clic de distancia</span>
                        </h1>
                        <p class="lp-hero__lead">
                            Abarrotes, bebidas, snacks y artículos de limpieza con los mejores
                            precios y promociones. Compra en línea y recíbelo donde estés.
                        </p>

                        <div class="lp-hero__cta">
                            <template v-if="autenticado">
                                <Link :href="inicio()" class="lp-btn lp-btn--accent lp-btn--lg">
                                    Ir a la tienda
                                </Link>
                            </template>
                            <template v-else>
                                <Link :href="register()" class="lp-btn lp-btn--accent lp-btn--lg">
                                    Crear cuenta y comprar
                                </Link>
                                <Link :href="login()" class="lp-btn lp-btn--outline lp-btn--lg">
                                    Ya tengo cuenta
                                </Link>
                            </template>
                        </div>

                        <ul class="lp-hero__points">
                            <li>✓ Registro gratis</li>
                            <li>✓ Envío a domicilio</li>
                            <li>✓ Pago al contado o en cuotas</li>
                        </ul>
                    </div>

                    <div class="lp-hero__media">
                        <img
                            v-if="imagenes.hero"
                            :src="imagenes.hero"
                            alt="Productos de la Tienda D & D"
                            class="lp-hero__img"
                        />
                        <div v-else class="lp-hero__img lp-ph lp-ph--hero">
                            <span>🛒</span>
                            <small>Imagen destacada</small>
                        </div>
                    </div>
                </div>
            </section>

            <!-- BENEFICIOS -->
            <section class="lp-features">
                <div class="lp-container lp-features__grid">
                    <div class="lp-feature">
                        <div class="lp-feature__icon" aria-hidden="true">🚚</div>
                        <h3>Envío a domicilio</h3>
                        <p>Recibe tu pedido en la puerta de tu casa, rápido y seguro.</p>
                    </div>
                    <div class="lp-feature">
                        <div class="lp-feature__icon" aria-hidden="true">🏷️</div>
                        <h3>Promociones</h3>
                        <p>Descuentos reales en tus productos favoritos, todo el mes.</p>
                    </div>
                    <div class="lp-feature">
                        <div class="lp-feature__icon" aria-hidden="true">💳</div>
                        <h3>Pagos flexibles</h3>
                        <p>Paga al contado, en cuotas o con QR desde tu celular.</p>
                    </div>
                    <div class="lp-feature">
                        <div class="lp-feature__icon" aria-hidden="true">⏱️</div>
                        <h3>Compra en minutos</h3>
                        <p>Un carrito simple y rápido para no perder tiempo.</p>
                    </div>
                </div>
            </section>

            <!-- CATEGORÍAS -->
            <section class="lp-cats">
                <div class="lp-container">
                    <div class="lp-section-head">
                        <h2>Compra por categoría</h2>
                        <p>Encuentra justo lo que buscas en nuestro catálogo.</p>
                    </div>

                    <div class="lp-cats__grid">
                        <article class="lp-cat">
                            <div class="lp-cat__media">
                                <img v-if="imagenes.abarrotes" :src="imagenes.abarrotes" alt="Abarrotes" />
                                <div v-else class="lp-ph"><span>🍚</span></div>
                            </div>
                            <h3>Abarrotes</h3>
                        </article>
                        <article class="lp-cat">
                            <div class="lp-cat__media">
                                <img v-if="imagenes.bebidas" :src="imagenes.bebidas" alt="Bebidas" />
                                <div v-else class="lp-ph"><span>🥤</span></div>
                            </div>
                            <h3>Bebidas</h3>
                        </article>
                        <article class="lp-cat">
                            <div class="lp-cat__media">
                                <img v-if="imagenes.limpieza" :src="imagenes.limpieza" alt="Limpieza" />
                                <div v-else class="lp-ph"><span>🧼</span></div>
                            </div>
                            <h3>Limpieza</h3>
                        </article>
                        <article class="lp-cat">
                            <div class="lp-cat__media">
                                <img v-if="imagenes.snacks" :src="imagenes.snacks" alt="Snacks" />
                                <div v-else class="lp-ph"><span>🍪</span></div>
                            </div>
                            <h3>Snacks</h3>
                        </article>
                    </div>
                </div>
            </section>

            <!-- CTA FINAL -->
            <section class="lp-cta">
                <div class="lp-container lp-cta__inner">
                    <div
                        class="lp-cta__media"
                        :style="imagenes.promo ? { backgroundImage: `url(${imagenes.promo})` } : undefined"
                        :class="{ 'lp-cta__media--ph': !imagenes.promo }"
                    ></div>
                    <div class="lp-cta__copy">
                        <h2>¿Listo para empezar a ahorrar?</h2>
                        <p>
                            Crea tu cuenta en segundos y empieza a comprar con precios de
                            conveniencia y promociones exclusivas.
                        </p>
                        <div class="lp-cta__buttons">
                            <template v-if="autenticado">
                                <Link :href="inicio()" class="lp-btn lp-btn--accent lp-btn--lg">
                                    Ir a la tienda
                                </Link>
                            </template>
                            <template v-else>
                                <Link :href="register()" class="lp-btn lp-btn--accent lp-btn--lg">
                                    Registrarse ahora
                                </Link>
                                <Link :href="login()" class="lp-btn lp-btn--outline-dark lp-btn--lg">
                                    Iniciar sesión
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="lp-footer">
            <div class="lp-container lp-footer__inner">
                <span class="lp-footer__brand">Tienda D &amp; D</span>
                <span class="lp-footer__copy">© {{ anio }} — Tu tienda de conveniencia</span>
                <span class="lp-footer__visits">👁 Visitas a esta página: {{ visitas }}</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/*
  Estilos FIJOS de la landing. Todo en px y colores hex propios (paleta del negocio: verde
  de conveniencia + acento naranja) para que NO reaccione a temas, tamaño de letra, alto
  contraste ni modo oscuro del sistema. Scoped => no filtra al resto de la app.
*/
.landing {
    --lp-verde: #1c8759;
    --lp-verde-osc: #12603d;
    --lp-naranja: #f97316;
    --lp-naranja-osc: #ea6a0a;
    --lp-crema: #fbf8f3;
    --lp-texto: #16241d;
    --lp-gris: #55655c;
    --lp-borde: #e6e0d6;

    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    font-size: 16px;
    line-height: 1.5;
    color: var(--lp-texto);
    background-color: var(--lp-crema);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    -webkit-font-smoothing: antialiased;
}

.landing * {
    box-sizing: border-box;
}

.lp-container {
    width: 100%;
    max-width: 1120px;
    margin: 0 auto;
    padding-left: 24px;
    padding-right: 24px;
}

/* Botones */
.lp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
    line-height: 1;
    padding: 11px 20px;
    border-radius: 10px;
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.15s ease, transform 0.05s ease, border-color 0.15s ease;
    white-space: nowrap;
}
.lp-btn:active {
    transform: translateY(1px);
}
.lp-btn--lg {
    font-size: 16px;
    padding: 14px 26px;
}
.lp-btn--primary {
    background-color: var(--lp-verde);
    color: #ffffff;
}
.lp-btn--primary:hover {
    background-color: var(--lp-verde-osc);
}
.lp-btn--accent {
    background-color: var(--lp-naranja);
    color: #ffffff;
}
.lp-btn--accent:hover {
    background-color: var(--lp-naranja-osc);
}
.lp-btn--ghost {
    background-color: transparent;
    color: var(--lp-texto);
}
.lp-btn--ghost:hover {
    background-color: rgba(28, 135, 89, 0.1);
}
.lp-btn--outline {
    background-color: #ffffff;
    color: var(--lp-verde-osc);
    border-color: var(--lp-borde);
}
.lp-btn--outline:hover {
    border-color: var(--lp-verde);
}
.lp-btn--outline-dark {
    background-color: transparent;
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.55);
}
.lp-btn--outline-dark:hover {
    background-color: rgba(255, 255, 255, 0.12);
}

/* Barra superior */
.lp-nav {
    background-color: #ffffff;
    border-bottom: 1px solid var(--lp-borde);
    position: sticky;
    top: 0;
    z-index: 20;
}
.lp-nav__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 68px;
}
.lp-brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--lp-texto);
}
.lp-brand__mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background-color: var(--lp-verde);
    color: #ffffff;
    font-weight: 800;
    font-size: 15px;
    letter-spacing: -0.5px;
}
.lp-brand__name {
    font-weight: 700;
    font-size: 18px;
}
.lp-nav__links {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Hero */
.lp-hero {
    background: linear-gradient(160deg, #eaf6ef 0%, var(--lp-crema) 60%);
    padding: 64px 0;
}
.lp-hero__grid {
    display: grid;
    grid-template-columns: 1.05fr 0.95fr;
    gap: 48px;
    align-items: center;
}
.lp-eyebrow {
    display: inline-block;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--lp-verde);
    background-color: rgba(28, 135, 89, 0.12);
    padding: 6px 12px;
    border-radius: 999px;
    margin-bottom: 18px;
}
.lp-hero__title {
    font-size: 46px;
    line-height: 1.08;
    font-weight: 800;
    letter-spacing: -1px;
    margin: 0 0 18px;
}
.lp-accent {
    color: var(--lp-naranja);
}
.lp-hero__lead {
    font-size: 18px;
    color: var(--lp-gris);
    margin: 0 0 28px;
    max-width: 520px;
}
.lp-hero__cta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}
.lp-hero__points {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 14px;
    font-weight: 600;
    color: var(--lp-verde-osc);
}
.lp-hero__media {
    position: relative;
}
.lp-hero__img {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: 20px;
    box-shadow: 0 24px 48px -20px rgba(18, 96, 61, 0.4);
}

/* Marcadores de posición cuando falta la imagen */
.lp-ph {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #dff0e7, #cfe7db);
    color: var(--lp-verde-osc);
}
.lp-ph span {
    font-size: 40px;
    line-height: 1;
}
.lp-ph small {
    font-size: 13px;
    font-weight: 600;
}
.lp-ph--hero span {
    font-size: 64px;
}

/* Beneficios */
.lp-features {
    padding: 56px 0;
}
.lp-features__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.lp-feature {
    background-color: #ffffff;
    border: 1px solid var(--lp-borde);
    border-radius: 16px;
    padding: 24px;
}
.lp-feature__icon {
    font-size: 30px;
    line-height: 1;
    margin-bottom: 12px;
}
.lp-feature h3 {
    font-size: 17px;
    font-weight: 700;
    margin: 0 0 6px;
}
.lp-feature p {
    font-size: 14px;
    color: var(--lp-gris);
    margin: 0;
}

/* Encabezado de sección */
.lp-section-head {
    text-align: center;
    margin-bottom: 36px;
}
.lp-section-head h2 {
    font-size: 32px;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin: 0 0 8px;
}
.lp-section-head p {
    font-size: 16px;
    color: var(--lp-gris);
    margin: 0;
}

/* Categorías */
.lp-cats {
    padding: 24px 0 64px;
}
.lp-cats__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.lp-cat {
    background-color: #ffffff;
    border: 1px solid var(--lp-borde);
    border-radius: 16px;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.lp-cat:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 32px -18px rgba(18, 96, 61, 0.45);
}
.lp-cat__media {
    aspect-ratio: 4 / 3;
    background-color: #eef4f0;
}
.lp-cat__media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.lp-cat h3 {
    font-size: 16px;
    font-weight: 700;
    text-align: center;
    margin: 0;
    padding: 16px;
}

/* CTA final */
.lp-cta {
    padding: 0 0 72px;
}
.lp-cta__inner {
    display: grid;
    grid-template-columns: 0.9fr 1.1fr;
    background: linear-gradient(135deg, var(--lp-verde) 0%, var(--lp-verde-osc) 100%);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 24px 48px -24px rgba(18, 96, 61, 0.5);
}
.lp-cta__media {
    background-size: cover;
    background-position: center;
    min-height: 240px;
}
.lp-cta__media--ph {
    background: linear-gradient(135deg, #2aa06c, #17784f);
}
.lp-cta__copy {
    padding: 48px 44px;
    color: #ffffff;
}
.lp-cta__copy h2 {
    font-size: 30px;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin: 0 0 12px;
}
.lp-cta__copy p {
    font-size: 16px;
    color: rgba(255, 255, 255, 0.9);
    margin: 0 0 24px;
    max-width: 440px;
}
.lp-cta__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

/* Pie */
.lp-footer {
    margin-top: auto;
    background-color: #ffffff;
    border-top: 1px solid var(--lp-borde);
}
.lp-footer__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding-top: 20px;
    padding-bottom: 20px;
    font-size: 13px;
    color: var(--lp-gris);
}
.lp-footer__brand {
    font-weight: 700;
    color: var(--lp-texto);
}

/* Responsivo */
@media (max-width: 900px) {
    .lp-hero__grid {
        grid-template-columns: 1fr;
        gap: 32px;
    }
    .lp-hero__title {
        font-size: 38px;
    }
    .lp-features__grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .lp-cats__grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .lp-cta__inner {
        grid-template-columns: 1fr;
    }
    .lp-cta__media {
        min-height: 160px;
    }
}

@media (max-width: 520px) {
    .lp-hero__title {
        font-size: 30px;
    }
    .lp-features__grid,
    .lp-cats__grid {
        grid-template-columns: 1fr;
    }
    .lp-brand__name {
        display: none;
    }
    .lp-btn {
        padding: 10px 14px;
        font-size: 14px;
    }
}
</style>
