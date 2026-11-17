/** Test double of sortablejs: records the instances and their options. */
export type SortableOptions = {
  onMove?: (evt: unknown) => boolean | void;
  onEnd?: (evt: unknown) => void;
  [key: string]: unknown;
};

export default class Sortable {
  static instances: Sortable[] = [];
  static byElement = new Map<HTMLElement, Sortable>();
  destroyed = false;

  constructor(
    readonly element: HTMLElement,
    readonly options: SortableOptions,
  ) {
    Sortable.instances.push(this);
    Sortable.byElement.set(element, this);
  }

  static get(element: HTMLElement): Sortable | undefined {
    return Sortable.byElement.get(element);
  }

  static reset(): void {
    Sortable.instances = [];
    Sortable.byElement = new Map();
  }

  destroy(): void {
    this.destroyed = true;
    Sortable.byElement.delete(this.element);
  }
}
