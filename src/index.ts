import { omit } from 'lodash';

import { addAction } from '@wordpress/hooks';

import { registerModule } from '@divi/module-library';

import { csvEventsModule } from './components/csv-events-module';

import './module-icons';

// Register the module.
addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'diviCsvEvents', () => {
  registerModule(csvEventsModule.metadata, omit(csvEventsModule, 'metadata'));
});
