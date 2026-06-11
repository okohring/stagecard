# Stagecard

Stagecard is a WordPress plugin designed for creating, managing, and customizing program agendas, including individual speaker, event, and sponsor pages. 

Made in partnership with ChatGPT. 

## Current updater workflow

This repository hosts Stagecard releases for WordPress plugin updates.

Each client-ready version should have:

1. A matching plugin version in `program-agenda/program-agenda.php`.
2. A matching GitHub release tag, such as `v1.15.144`.
3. A plugin ZIP attached to the release, such as `program-agenda-v1-15-144.zip`.

The updater inside Stagecard checks this GitHub repository for the latest release and downloads the uploaded plugin ZIP asset.

## Important packaging note

The plugin ZIP must contain the plugin folder at the top level:

```text
program-agenda/
  program-agenda.php
  uninstall.php
  assets/
```

Do not use GitHub's automatic "Source code" ZIP as the WordPress plugin ZIP. Use the packaged plugin ZIP that contains the correct `program-agenda` folder.


Try Stagecard in WordPress Playground

(https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fokohring%2Fstagecard%2Frefs%2Fheads%2Fmain%2Fblueprint.json)
