# CSS Critique (Critical CSS)

## À quoi ça sert ?

Le CSS Critique permet d'afficher le contenu visible immédiatement (au-dessus de la ligne de flottaison) sans attendre le chargement complet des feuilles de style. Le reste du CSS est chargé en arrière-plan. Résultat : le LCP (temps d'affichage du contenu principal) peut s'améliorer de 200 ms à 800 ms.

---

## Activation

1. Aller dans **LWS Optimize → Optimisations front-end**.
2. Repérer la section **Critical CSS**.
3. Cocher la case pour activer.
4. Un panneau de configuration s'ouvre en dessous — choisir le mode (voir ci-dessous).
5. Sauvegarder.

---

## Choisir le mode

| Mode | Quand l'utiliser |
|------|-----------------|
| **Auto — génération locale** *(recommandé)* | Pour la plupart des sites. Le plugin analyse chaque page automatiquement et génère le CSS critique sans aucun service externe. |
| **Auto — service LWS** | Si le mode local donne des résultats insuffisants. Le plugin envoie les données au service LWS pour une génération plus précise, avec fallback local en cas d'indisponibilité. |
| **Manuel** | Pour les utilisateurs avancés qui souhaitent coller leur propre CSS critique (obtenu via un outil tiers, par exemple). |

---

## Mode Manuel

Si vous choisissez **Manuel**, une zone de texte apparaît. Coller le CSS critique dans ce champ, puis sauvegarder. Ce CSS sera appliqué à toutes les pages du site.

---

## Comportement après activation

- En mode **Auto**, le CSS critique n'est pas actif immédiatement à la première visite d'une page : le plugin planifie sa génération en arrière-plan. Dès la deuxième visite, le CSS critique est mis en cache et s'applique. Ce cache dure **7 jours**.
- Le cache est automatiquement invalidé quand un article ou une page est mis à jour (via Save Post).

---

## Notes

- Si votre site utilise un thème ou des plugins qui chargent du CSS de façon dynamique (sliders, modales), certains styles peuvent être manquants après activation. Dans ce cas, passer en mode **Manuel** et corriger le CSS.
- Le mode auto n'analyse pas les CSS hébergés sur des domaines tiers (Google Fonts, CDN externe) — seul le CSS de votre propre domaine est inclus.
