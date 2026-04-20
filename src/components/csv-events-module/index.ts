// Divi dependencies.
import {
  type Metadata,
  type ModuleLibrary,
} from '@divi/types';

// Local dependencies.
import metadataJson from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { CsvEventsModuleEdit } from './edit';
import { CsvEventsModuleAttrs } from './types';
import { placeholderContent } from './placeholder-content';

// Styles.
import './style.scss';
import './module.scss';

// Attach conditional visibility to the mode-gated fields. Divi 5 accepts a
// function on Field.ContainerProps.visible that receives current attrs.
const metadata: any = metadataJson;

const readMode = (attrs: any): string =>
  attrs?.csvSourceMode?.innerContent?.desktop?.value?.mode || 'file';

metadata.attributes.csvSource.settings.innerContent.items.src.visible =
  ({ attrs }: { attrs: any }) => readMode(attrs) === 'file';

metadata.attributes.csvContent.settings.innerContent.items.content.visible =
  ({ attrs }: { attrs: any }) => readMode(attrs) === 'paste';

export const csvEventsModule: ModuleLibrary.Module.RegisterDefinition<CsvEventsModuleAttrs> = {
  metadata:                 metadata as Metadata.Values<CsvEventsModuleAttrs>,
  defaultAttrs:             defaultRenderAttributes as Metadata.DefaultAttributes<CsvEventsModuleAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<CsvEventsModuleAttrs>,
  placeholderContent,
  renderers: {
    edit: CsvEventsModuleEdit,
  },
};
