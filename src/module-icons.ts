import { addFilter } from '@wordpress/hooks';
import { csvEventsIcon } from './icons';

// Add module icons to the icon library.
addFilter('divi.iconLibrary.icon.map', 'diviCsvEvents', (icons) => {
  return {
    ...icons,
    [csvEventsIcon.name]: csvEventsIcon,
  };
});
