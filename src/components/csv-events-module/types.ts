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

export interface EventSettingsValue {
  period?: string;
  count?: string;
  showPast?: string;
  view?: string;
  showFilter?: string;
  showViewSwitcher?: string;
  accentColor?: string;
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

  // CSV Source URL (text field)
  csvSource?: {
    innerContent?: FormatBreakpointStateAttr<string>;
  };

  // Heading
  heading?: Element.Types.Title.Attributes;

  // Event settings (all in one object with sub-values)
  eventSettings?: {
    innerContent?: FormatBreakpointStateAttr<EventSettingsValue>;
  };
}

export type CsvEventsModuleEditProps = ModuleEditProps<CsvEventsModuleAttrs>;

// Event data structure from REST API
export interface CsvEvent {
  date: string;
  time: string;
  title: string;
  location: string;
  description: string;
}
