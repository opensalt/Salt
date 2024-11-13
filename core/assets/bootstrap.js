import { startStimulusApp, registerControllers } from "vite-plugin-symfony/stimulus/helpers"
import { registerVueControllerComponents } from "vite-plugin-symfony/stimulus/helpers/vue"

// register Vue components before startStimulusApp
registerVueControllerComponents(import.meta.glob('./vue/controllers/**/*.vue'))

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

const app = startStimulusApp();
registerControllers(
  app,
  import.meta.glob(
    "./controllers/*_controller.js",
    {
      query: "?stimulus",
      eager: true,
    },
  ),
);
