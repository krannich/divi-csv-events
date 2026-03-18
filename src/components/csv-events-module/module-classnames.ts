import { ModuleClassnamesParams, textOptionsClassnames } from '@divi/module';
import { CsvEventsModuleAttrs } from './types';

/**
 * Module classnames function for CSV Events Module.
 *
 * @since 1.0.0
 */
export const moduleClassnames = ({
  classnamesInstance,
  attrs,
}: ModuleClassnamesParams<CsvEventsModuleAttrs>): void => {
  classnamesInstance.add(textOptionsClassnames(attrs?.module?.advanced?.text));
};
