import { Controller } from '@hotwired/stimulus';

/**
 * Example Stimulus controller.
 * Any element with data-controller="hello" will run this.
 */
export default class extends Controller {
  connect(): void {
    this.element.textContent = 'Hello Stimulus! Edit me in assets/controllers/hello_controller.ts';
  }
}
