// Divi dependencies.
import {
  type Metadata,
  type ModuleLibrary,
} from '@divi/types';

// Local dependencies.
import metadata from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { CsvEventsModuleEdit } from './edit';
import { CsvEventsModuleAttrs } from './types';
import { placeholderContent } from './placeholder-content';

// Styles.
import './style.scss';
import './module.scss';

export const csvEventsModule: ModuleLibrary.Module.RegisterDefinition<CsvEventsModuleAttrs> = {
  metadata:                 metadata as Metadata.Values<CsvEventsModuleAttrs>,
  defaultAttrs:             defaultRenderAttributes as Metadata.DefaultAttributes<CsvEventsModuleAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<CsvEventsModuleAttrs>,
  placeholderContent,
  renderers: {
    edit: CsvEventsModuleEdit,
  },
};
