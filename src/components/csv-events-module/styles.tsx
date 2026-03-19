// External dependencies.
import React, { ReactElement } from 'react';

// Divi dependencies.
import {
  StyleContainer,
  StylesProps,
  CssStyle,
} from '@divi/module';

// Local dependencies.
import { CsvEventsModuleAttrs } from './types';
import { cssFields } from './custom-css';

/**
 * CSV Events Module style components.
 *
 * @since 1.0.0
 */
export const ModuleStyles = ({
    attrs,
    elements,
    settings,
    orderClass,
    mode,
    state,
    noStyleTag,
  }: StylesProps<CsvEventsModuleAttrs>): ReactElement => {

  return (
    <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
      {/* Module */}
      {elements.style({
        attrName: 'module',
        styleProps: {
          disabledOn: {
            disabledModuleVisibility: settings?.disabledModuleVisibility,
          },
        },
      })}

      {/* Heading */}
      {elements.style({
        attrName: 'heading',
      })}

      {/* Date Text */}
      {elements.style({
        attrName: 'dateText',
      })}

      {/* Title Text */}
      {elements.style({
        attrName: 'titleText',
      })}

      {/* Meta Text */}
      {elements.style({
        attrName: 'metaText',
      })}

      {/* Description Text */}
      {elements.style({
        attrName: 'descText',
      })}

      {/* Filter Buttons */}
      {elements.style({
        attrName: 'filterBtn',
      })}

      <CssStyle
        selector={orderClass}
        attr={attrs.css}
        cssFields={cssFields}
      />
    </StyleContainer>
  );
};
