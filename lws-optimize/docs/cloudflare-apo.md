# Cloudflare APO — Cache HTML edge

## À quoi ça sert ?

Cloudflare APO met en cache les pages HTML de votre site directement sur les serveurs Cloudflare dans le monde entier. Résultat : vos pages sont servies depuis le serveur Cloudflare le plus proche du visiteur, avec un TTFB (temps de réponse) d'environ 50 ms partout dans le monde, et une charge serveur divisée par 10.

La purge du cache Cloudflare est synchronisée automatiquement avec LWS Optimize : quand vous modifiez un article ou une page, les caches Cloudflare et LWS sont vidés ensemble.

---

## Prérequis

- Un compte Cloudflare avec votre domaine configuré.
- Un **token API Cloudflare** avec les permissions suivantes :
  - `Zone.Cache Purge` (Edit)
  - `Zone.Cache Rules` (Edit)
- Le **Zone ID** de votre domaine (visible dans le tableau de bord Cloudflare, colonne de droite de la page d'accueil de la zone).

---

## Activation

### Étape 1 — Activer l'intégration Cloudflare de base

1. Aller dans **LWS Optimize → CDN**.
2. Activer la case **Intégration Cloudflare avec LWS Optimize** (section du haut).
3. Sauvegarder.

> Sans cette étape, la section APO reste grisée et inutilisable.

### Étape 2 — Configurer l'APO

1. Toujours dans **LWS Optimize → CDN**, faire défiler jusqu'à la section **Cloudflare APO — cache HTML edge**.
2. Renseigner :
   - **Zone ID Cloudflare** : identifiant de votre zone (ex. `a1b2c3d4e5f6...`).
   - **Token API** : token créé dans le tableau de bord Cloudflare.
3. Cliquer sur **Installer la Cache Rule sur Cloudflare** pour créer automatiquement la règle de cache sur votre zone.
4. Cocher la case pour **activer l'APO**.
5. Sauvegarder.

---

## Ce que fait "Installer la Cache Rule"

Ce bouton crée (ou remplace) une règle dans Cloudflare qui met en cache le HTML pour les visiteurs anonymes. Elle exclut automatiquement :
- Les pages d'administration (`/wp-admin`, `/wp-login`)
- Les visiteurs connectés (cookies `wordpress_logged_in_`, `woocommerce_`, etc.)

Le cache HTML est conservé **8 heures** sur les serveurs Cloudflare, et vidé automatiquement à chaque modification de contenu.

---

## Purge manuelle du cache

En plus de la purge automatique, il est possible de vider tout le cache Cloudflare manuellement via WP-CLI :

```bash
wp lwsoptimize cloudflare purge-all
```

---

## Notes

- L'APO est compatible avec WooCommerce : les pages panier, commande et les pages visitées par un client connecté ne sont jamais mises en cache.
- Si vous désactivez l'APO, la Cache Rule reste présente sur Cloudflare mais n'est plus alimentée par le plugin. Il faut la supprimer manuellement depuis le tableau de bord Cloudflare si besoin.
