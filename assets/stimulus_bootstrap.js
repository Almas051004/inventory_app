import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

// Disable Turbo globally to prevent SPA-like behavior
if (typeof Turbo !== 'undefined') {
    Turbo.session.drive = false;
}
