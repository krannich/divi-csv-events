import { addFilter } from '@wordpress/hooks';
import FilePicker from './fields/file-picker';

// Register custom field components in Divi's field library.
addFilter('divi.fieldLibrary.field.map', 'diviCsvEvents', (fields: Record<string, any>) => {
  return {
    ...fields,
    'dcsve/file-picker': FilePicker,
  };
});
