import {
  ModuleFlatObjectNamed,
  ModuleFlatObjects,
  type EditPost,
} from '@divi/types';

export type ModuleFlatObjectItems = (
  ModuleFlatObjectNamed<'dcsve/csv-events'>
);

export type DcsveFlatObjects = ModuleFlatObjects<ModuleFlatObjectItems>;

export type DcsveEditPostStoreState = EditPost.Store.State<DcsveFlatObjects>;

export type DcsveImmutableEditPostStoreState = EditPost.Store.ImmutableState<DcsveFlatObjects>;
