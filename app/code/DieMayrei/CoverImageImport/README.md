# Diemayrei CoverImageImport

Magento 2 Modul zum automatischen Import von Zeitschriften-Coverbildern für Produkte und Kategorien.

## Features

- Automatischer Download und Resize von Cover-Bildern
- Zuweisung von Covers zu Produkten und Kategorien
- Unterstützung für Print- und Digital-Ausgaben
- Cron-basierter automatischer Import (täglich um 4:00 und 16:00 Uhr)
- CLI-Befehl für manuellen Import
- Zusätzliche Kategorie-Attribute (Thumbnail, Support-Kontakt, Kurzbeschreibung)

## Konfigurierte Zeitschriften

Die verfügbaren Zeitschriften werden zentral in `Model/MagazineConfig.php` definiert:

- **Kaninchenzeitung** (Print + Digital)
- **Geflügelzeitung** (Print + Digital)

### Neue Zeitschrift hinzufügen

1. `Model/MagazineConfig.php` bearbeiten und neue Zeitschrift im `MAGAZINES` Array hinzufügen:

```php
private const MAGAZINES = [
    'kaninchenzeitung' => [
        'label' => 'Kaninchenzeitung',
        'has_digital' => true
    ],
    'gefluegelzeitung' => [
        'label' => 'Geflügelzeitung',
        'has_digital' => true
    ],
    // Neue Zeitschrift hinzufügen:
    'neue_zeitschrift' => [
        'label' => 'Neue Zeitschrift',
        'has_digital' => true  // oder false wenn keine Digital-Version
    ],
];
```

2. `etc/adminhtml/system.xml` bearbeiten und neue Gruppe für die Admin-Konfiguration hinzufügen.

3. Cache leeren: `bin/magento cache:flush`

## Installation

```bash
bin/magento module:enable Diemayrei_CoverImageImport
bin/magento setup:upgrade
bin/magento cache:flush
```

## CLI-Befehle

```bash
# Cover-Bilder manuell importieren
bin/magento diemayrei:cover:import
```

## Admin-Konfiguration

Die Cover-URLs werden unter **Stores > Configuration > Diemayrei > Cover Import** konfiguriert.

## Architektur

```
CoverImageImport/
├── Block/Category/View.php          # Frontend-Block für Kategorie-Ansicht
├── Console/Command/ImportCover.php  # CLI-Befehl
├── Controller/Adminhtml/            # Admin-Controller
├── Cron/FetchCover.php              # Cron-Job Orchestrierung
├── Helper/CoverImageImport.php      # Konfigurationshelfer
├── Model/
│   ├── MagazineConfig.php           # Zentrale Zeitschriften-Definition
│   ├── Category/Attribute/Source/   # Dropdown-Optionen
│   ├── Config/Backend/              # Backend-Modelle
│   ├── ImageImport.php              # Datenmodell
│   └── ResourceModel/               # Datenbankzugriff
├── Service/
│   ├── ImageDownloader.php          # Bild-Download & Resize
│   ├── CategoryImageUpdater.php     # Kategorie-Bild-Updates
│   └── ProductImageUpdater.php      # Produkt-Bild-Updates
├── Setup/Patch/Data/                # Datenbank-Patches
├── etc/                             # Modul-Konfiguration
└── view/adminhtml/                  # Admin-UI-Komponenten
```

## Technische Details

- Bilder werden mit Imagick auf 600px Breite resized
- Komprimierungsqualität: 70%
- Auflösung: 72 DPI
- Unterstützte Formate: JPG, PNG, GIF

## Datenbank

Das Modul verwendet die Tabelle `cover_image_import` zur Tracking der importierten Bilder:

| Spalte       | Typ       | Beschreibung                    |
|--------------|-----------|----------------------------------|
| id           | int       | Primary Key                      |
| key          | varchar   | Config-Pfad der Zeitschrift      |
| origin       | varchar   | Original-URL des Bildes          |
| imported     | varchar   | Lokaler Pfad des importierten Bildes |
| last_updated | timestamp | Zeitstempel des letzten Updates  |
