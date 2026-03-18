import React, {
  Fragment,
  ReactElement,
} from 'react';

import {
  ModuleScriptDataProps,
} from '@divi/module';
import { CsvEventsModuleAttrs } from './types';

/**
 * CSV Events Module script data component.
 *
 * @since 1.0.0
 */
export const ModuleScriptData = ({
  elements,
}: ModuleScriptDataProps<CsvEventsModuleAttrs>): ReactElement => (
  <Fragment>
    {elements.scriptData({
      attrName: 'module',
    })}
  </Fragment>
);
