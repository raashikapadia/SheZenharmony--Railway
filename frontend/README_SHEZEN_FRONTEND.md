# SheZen Flutter starter

The bootstrap script creates the base Flutter project, adds the `http` dependency,
then overlays the files in this starter.

## Development API address

Android emulator:

`http://10.0.2.2:8000/api`

Physical Android device:

```powershell
flutter run --dart-define=API_BASE_URL=http://YOUR_PC_LAN_IP:8000/api
```

## Security note

`android/app/src/debug/AndroidManifest.xml` allows clear-text HTTP **only for local
development**. Production must use HTTPS.
