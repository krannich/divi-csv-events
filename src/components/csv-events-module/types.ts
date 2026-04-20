// WordPress REST API settings on window.
declare global {
  interface Window {
    wpApiSettings?: { root: string; nonce: string };
  }
}

// Divi dependencies.
import { ModuleEditProps } from '@divi/module-library';
import {
  FormatBreakpointStateAttr,
  InternalAttrs,
  type Element,
  type Module,
} from '@divi/types';

export interface CsvEventsModuleCssAttr extends Module.Css.AttributeValue {
  heading?: string;
  controls?: string;
  content?: string;
}

export type CsvEventsModuleCssGroupAttr = FormatBreakpointStateAttr<CsvEventsModuleCssAttr>;

export type CsvSourceMode = 'file' | 'paste';

export interface CsvSourceModeValue {
  mode?: CsvSourceMode;
}

export interface CsvSourceValue {
  src?: string;
}

export interface CsvContentValue {
  content?: string;
}

export interface EventSettingsValue {
  period?: string;
  periodCount?: string;
  count?: string;
  showPast?: string;
  view?: string;
  showFilter?: string;
  showViewSwitcher?: string;
  accentColor?: string;
  organizerName?: string;
  organizerUrl?: string;
  schemaEnabled?: string;
}

export interface CsvEventsModuleAttrs extends InternalAttrs {
  css?: CsvEventsModuleCssGroupAttr;

  module?: {
    meta?: Element.Meta.Attributes;
    advanced?: {
      link?: Element.Advanced.Link.Attributes;
      htmlAttributes?: Element.Advanced.IdClasses.Attributes;
      text?: Element.Advanced.Text.Attributes;
    };
    decoration?: Element.Decoration.PickedAttributes<
      'animation' |
      'background' |
      'border' |
      'boxShadow' |
      'disabledOn' |
      'filters' |
      'overflow' |
      'position' |
      'scroll' |
      'sizing' |
      'spacing' |
      'sticky' |
      'transform' |
      'transition' |
      'zIndex'
    > & {
      attributes?: any;
    };
  };

  // CSV source mode selector (file | paste)
  csvSourceMode?: {
    innerContent?: FormatBreakpointStateAttr<CsvSourceModeValue>;
  };

  // CSV source URL (upload field, active when mode=file)
  csvSource?: {
    innerContent?: FormatBreakpointStateAttr<CsvSourceValue>;
  };

  // CSV pasted content (textarea + modal, active when mode=paste)
  csvContent?: {
    innerContent?: FormatBreakpointStateAttr<CsvContentValue>;
  };

  // Heading
  heading?: Element.Types.Title.Attributes;

  // Font decoration elements
  dateText?:  { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  titleText?: { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  metaText?:  { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  descText?:  { decoration?: { font?: Element.Decoration.Font.Attributes; }; };
  filterBtn?: { decoration?: { font?: Element.Decoration.Font.Attributes; }; };

  eventSettings?: {
    innerContent?: FormatBreakpointStateAttr<EventSettingsValue>;
  };
}

export type CsvEventsModuleEditProps = ModuleEditProps<CsvEventsModuleAttrs>;

export interface CsvEvent {
  date: string;
  time: string;
  start_time?: string;
  end_time?: string;
  title: string;
  location: string;
  description: string;
  address?: string;
}
