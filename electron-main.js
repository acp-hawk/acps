const { app, BrowserWindow, Tray, Menu, nativeImage, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');

// --- APP IDENTITY FOR WINDOWS ---
app.setAppUserModelId('com.alleycatphoto.acps90');

let mainWindow;
let settingsWindow;
let tray;
let store;

// --- SINGLE INSTANCE LOCK ---
const gotTheLock = app.requestSingleInstanceLock();

if (!gotTheLock) {
  app.quit();
} else {
  app.on('second-instance', (event, commandLine, workingDirectory) => {
    // Someone tried to run a second instance, we should focus our window.
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.show();
      mainWindow.focus();
    }
  });

  // Default Configuration
  const DEFAULT_URL = 'http://localhost/admin/';
  const ICON_PATH = path.join(__dirname, 'logo.png');

  // Initialize Store (using dynamic import for ESM compatibility)
  async function initStore() {
    const { default: Store } = await import('electron-store');
    store = new Store();
  }

  function getTargetUrl() {
    if (!store) return DEFAULT_URL;
    return store.get('targetUrl', DEFAULT_URL);
  }

  function createSettingsWindow() {
    if (settingsWindow) {
      settingsWindow.focus();
      return;
    }

    settingsWindow = new BrowserWindow({
      width: 500,
      height: 350,
      title: 'Connection Settings',
      icon: ICON_PATH,
      backgroundColor: '#0a0a0a',
      webPreferences: {
        nodeIntegration: true, // Needed for simple IPC in settings page
        contextIsolation: false
      },
      autoHideMenuBar: true,
      parent: mainWindow,
      modal: true,
      show: false
    });

    settingsWindow.loadFile('electron-settings.html');
    
    settingsWindow.once('ready-to-show', () => {
      settingsWindow.show();
    });

    settingsWindow.on('closed', () => {
      settingsWindow = null;
    });
  }

  function createWindow() {
    const targetUrl = getTargetUrl();

    mainWindow = new BrowserWindow({
      width: 800,
      height: 950,
      title: 'AlleyCat PhotoStation : Admin',
      icon: ICON_PATH,
      backgroundColor: '#0a0a0a', // Matches Gemicunt theme
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        // preload: path.join(__dirname, 'preload.js') // Optional
      },
      autoHideMenuBar: true, // Show menu for access to settings
      show: false // Don't show until ready-to-show to avoid white flash
    });

    // Load the PHP Application
    mainWindow.loadURL(targetUrl).catch(err => {
        console.log(`Failed to load URL: ${targetUrl}`);
        mainWindow.loadFile('electron-error.html');
    });

    mainWindow.once('ready-to-show', () => {
      mainWindow.show();
    });

    // Inject Custom Dark Theme (Gemicunt Style)
    mainWindow.webContents.on('did-finish-load', () => {
      const cssPath = path.join(__dirname, 'electron-theme.css');
      fs.readFile(cssPath, 'utf-8', (err, data) => {
        if (!err) {
          mainWindow.webContents.insertCSS(data);
        } else {
          console.error("Could not load theme CSS");
        }
      });
    });

    // Minimize to Tray behavior
    mainWindow.on('minimize', (event) => {
      event.preventDefault();
      mainWindow.hide();
    });

    // Close to Tray behavior
    mainWindow.on('close', (event) => {
      if (!app.isQuitting) {
        event.preventDefault();
        mainWindow.hide();
      }
      return false;
    });

    buildMenu();
  }

  function buildMenu() {
    const template = [
      {
        label: 'File',
        submenu: [
          { label: 'Quit', click: () => { app.isQuitting = true; app.quit(); } }
        ]
      },
      {
        label: 'View',
        submenu: [
          { role: 'reload' },
          { role: 'forceReload' },
          { type: 'separator' },
          { role: 'toggleDevTools' }
        ]
      },
      {
        label: 'Settings',
        submenu: [
          { 
            label: 'Connection...', 
            click: () => createSettingsWindow() 
          }
        ]
      }
    ];
    const menu = Menu.buildFromTemplate(template);
    Menu.setApplicationMenu(menu);
  }

  function createTray() {
    if (tray) return; // Guard against multiple tray icons

    const icon = nativeImage.createFromPath(ICON_PATH);
    tray = new Tray(icon);
    
    const contextMenu = Menu.buildFromTemplate([
      { label: 'Admin Console', click: () => mainWindow.show() },
      { label: 'Connection Settings...', click: () => createSettingsWindow() },
      { type: 'separator' },
      { label: 'Quit', click: () => {
          app.isQuitting = true;
          app.quit();
        } 
      }
    ]);

    tray.setToolTip('AlleyCat PhotoStation : Admin Console');
    tray.setContextMenu(contextMenu);

    tray.on('click', () => {
      if (mainWindow.isVisible()) {
        mainWindow.hide();
      } else {
        mainWindow.show();
      }
    });
  }

  // IPC Handlers for Settings
  ipcMain.handle('get-settings', async () => {
    return { targetUrl: getTargetUrl() };
  });

  ipcMain.on('save-settings', (event, { targetUrl }) => {
    if (store) {
      store.set('targetUrl', targetUrl);
    }
    if (settingsWindow) {
      settingsWindow.close();
    }
    // Reload main window with new URL
    if (mainWindow) {
      mainWindow.loadURL(targetUrl).catch(() => mainWindow.loadFile('electron-error.html'));
    }
  });

  app.whenReady().then(async () => {
    if (!gotTheLock) return; // Guard
    await initStore();
    createWindow();
    createTray();

    app.on('activate', () => {
      if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
  });

  // Quit when all windows are closed, except on macOS (standard behavior)
// But since we have a tray, we might want to keep running? 
// For this specific kiosk app, keeping it running in tray is usually desired.
  app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
      // app.quit(); // We don't quit here because we want to stay in tray
    }
  });
} // End of single instance else block
