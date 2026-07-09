# RUM — Real User Monitoring

## À quoi ça sert ?

Le RUM mesure les performances de votre site telles que vécues par vos vrais visiteurs (temps de chargement, réactivité, stabilité visuelle). Ces données apparaissent dans un tableau de bord dédié pour identifier les pages les plus lentes.

Aucune donnée personnelle n'est collectée : pas d'adresse IP enregistrée, pas de cookie, conforme RGPD.

---

## Activation

1. Aller dans **LWS Optimize → Optimisations front-end**.
2. Repérer la section **Real User Monitoring (RUM)**.
3. Cocher la case pour activer.
4. Sauvegarder.

Une fois activé, le plugin injecte automatiquement un petit script sur chaque page publique. Les données commencent à être collectées dès la première visite.

---

## Consulter les résultats

Aller dans **LWS Optimize → RUM** (entrée de menu dédiée).

Le tableau de bord affiche les 20 pages les plus lentes, triées par LCP. Pour chaque page, on voit les métriques suivantes au **p75** (75 % des visiteurs ont eu ce résultat ou mieux) :

| Métrique | Ce que ça mesure | Seuil "bon" |
|----------|-----------------|-------------|
| **LCP** | Temps avant que le contenu principal soit visible | ≤ 2,5 s |
| **INP** | Réactivité aux clics et interactions | ≤ 200 ms |
| **CLS** | Stabilité de la page (éléments qui bougent) | ≤ 0,1 |
| **TTFB** | Temps de réponse du serveur | ≤ 800 ms |

Un indicateur coloré (vert / orange / rouge) signale l'état de chaque métrique selon les seuils Google Core Web Vitals.

---

## Actions disponibles dans le tableau de bord

- **Filtrer par appareil** : choisir "Desktop" ou "Mobile" via le filtre en haut du tableau.
- **Forcer l'agrégation** : bouton "Agréger maintenant" pour recalculer les statistiques immédiatement (sinon fait automatiquement deux fois par jour).
- **Purger les données** : bouton pour supprimer les mesures de plus de 30 jours.

---

## Notes

- Les données n'apparaissent pas instantanément : elles sont agrégées toutes les 12 h (ou à la demande via le bouton).
- Sur les sites à très fort trafic (>1 000 mesures en buffer), 1 requête sur 10 est enregistrée — les statistiques restent représentatives.
