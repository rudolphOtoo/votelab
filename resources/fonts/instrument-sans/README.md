# Instrument Sans (self-hosted)

- **Source:** Instrument Sans by The Instrument Sans Project Authors, https://github.com/Instrument/instrument-sans
- **Licence:** SIL Open Font License 1.1, in `OFL.txt` here. It is a copy of `resources/fonts/OFL.txt`, fetched in Stage 2d from https://raw.githubusercontent.com/Instrument/instrument-sans/7fa22308a3d0c94ee2b3cd537a1196b65db34a3e/OFL.txt (SHA-256 `9e27a72ed30eb49a08678f6a5d6ed98ec7ba5368f541637ee0683ec9134ef966`).
- **Files:** Latin subset (U+0000–00FF and common punctuation), WOFF2, weights 400, 500 and 600, as served by Bunny Fonts (the files the build used before D-120).
- **Loaded by:** `vite.config.js` (`local()` font provider) and the `@fonts` directive in the layouts; 400 and 600 are preloaded.
- **Not included:** the cedi sign ₵ (U+20B5) isn't in Instrument Sans; amounts are written "GHS 50.00" (D-121).
