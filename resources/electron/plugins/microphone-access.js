import { app, session, systemPreferences } from 'electron';

// Electron ne sproži macOS vprašanja za mikrofon; brez dovoljenja getUserMedia uspe, a vrača tišino.
const handleMediaPermission = (webContents, permission, callback, details) => {
    if (process.platform !== 'darwin' || permission !== 'media' || !details.mediaTypes?.includes('audio')) {
        callback(true);

        return;
    }

    systemPreferences.askForMediaAccess('microphone').then(callback, () => callback(false));
};

app.on('session-created', (createdSession) => {
    createdSession.setPermissionRequestHandler(handleMediaPermission);
});

app.whenReady().then(() => {
    session.defaultSession.setPermissionRequestHandler(handleMediaPermission);
});
