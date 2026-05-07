<?php
/**
 * Shared layout tokens for static / informational pages (privacy, terms, support, etc.).
 * Include immediately after header.php on those pages.
 */
?>
<style>
    :root {
        --card-bg: #ffffff;
        --card-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        --radius-card: 24px;
        --radius-inner: 12px;
    }

    .content-page-wrap {
        max-width: 920px;
        margin: 0 auto;
        padding: 2rem 3rem 4rem;
        flex: 1;
        width: 100%;
    }

    .content-card {
        background: var(--card-bg);
        border-radius: var(--radius-card);
        padding: 2rem;
        box-shadow: var(--card-shadow);
        margin-bottom: 1.25rem;
    }

    .content-card h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.35rem;
        letter-spacing: -0.5px;
    }

    .content-card .page-lead {
        color: var(--text-medium);
        font-size: 1rem;
        margin-bottom: 1.5rem;
        line-height: 1.55;
    }

    .content-card h2 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-top: 1.5rem;
        margin-bottom: 0.5rem;
    }

    .content-card p,
    .content-card li {
        color: var(--text-medium);
        line-height: 1.65;
        font-size: 0.95rem;
    }

    .content-card ul {
        padding-left: 1.25rem;
        margin: 0.35rem 0 1rem;
    }

    .content-meta {
        font-size: 0.85rem;
        color: var(--text-light);
        margin-bottom: 1rem;
    }

    .btn-primary-inline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: var(--btn-gradient);
        color: white;
        padding: 0.85rem 1.75rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(61, 123, 244, 0.3);
        transition: all 0.35s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .btn-primary-inline:hover {
        transform: translateY(-2px);
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(61, 123, 244, 0.35);
    }

    .btn-secondary-inline {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        color: var(--text-dark);
        border: 2px solid var(--border-light);
        background: var(--bg-right);
        transition: all 0.3s ease;
    }

    .btn-secondary-inline:hover {
        border-color: var(--primary-blue);
        color: var(--primary-blue);
    }

    .grid-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.25rem;
        margin-top: 1rem;
    }

    .resource-card {
        background: var(--card-bg);
        border-radius: var(--radius-card);
        padding: 2rem;
        box-shadow: var(--card-shadow);
    }

    .resource-card .icon-wrap {
        width: 46px;
        height: 46px;
        border-radius: var(--radius-inner);
        background: var(--input-bg);
        color: var(--primary-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }

    .resource-card h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.45rem;
    }

    .resource-card p {
        font-size: 0.9rem;
        color: var(--text-medium);
        line-height: 1.55;
        margin-bottom: 1rem;
    }

    .faq-item {
        background: var(--input-bg);
        border-radius: var(--radius-inner);
        padding: 1.15rem 1.25rem;
        margin-bottom: 0.75rem;
    }

    .faq-item strong {
        display: block;
        color: var(--text-dark);
        font-size: 0.95rem;
        margin-bottom: 0.35rem;
    }

    .faq-item p {
        margin: 0;
        font-size: 0.9rem;
    }

    .inline-links {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem 1.25rem;
        margin-top: 1rem;
    }

    .inline-links a {
        color: var(--primary-blue);
        font-weight: 600;
        text-decoration: none;
        font-size: 0.95rem;
    }

    .inline-links a:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .content-page-wrap {
            padding: 1.25rem 1rem 3rem;
        }

        .content-card h1 {
            font-size: 1.55rem;
        }
    }
</style>
