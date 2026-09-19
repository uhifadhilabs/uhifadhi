# UPGRADE FROM 0.x to 1.0

## PostGIS comes from `utafitilabs/postgis-bundle`

The spatial repository base and the geometry DBAL types the area's entities are
mapped with now come from `utafitilabs/postgis-bundle`. Both packages register
the same DBAL type names, so the old one cannot stay alongside it:

```bash
composer remove fundistadi/postgis-bundle
composer require utafitilabs/postgis-bundle:^0.1
```

In `config/bundles.php`:

```diff
-FundiStadi\PostGISBundle\FundiStadiPostGISBundle::class => ['all' => true],
+UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle::class => ['all' => true],
```

And if the installation configures the bundle, its root key is now
`utafiti_labs_post_gis`, not `fundi_stadi_post_gis`. Nothing about the database
changes: the extension, the columns and the indexes are the same.
