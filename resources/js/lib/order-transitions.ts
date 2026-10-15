// Statuses an order moves forward through, in order (cancel and "undo ship" are separate, confirmed actions)
const FORWARD = ['processing', 'shipped', 'delivered'];

/**
 * The forward moves the status buttons and the edit form may offer: those the server allows from the current status
 * (Admin\OrdersController passes allowed_transitions), except "shipped". An order ships only through the ship form
 * (carrier, tracking number, one shipped email); the server refuses "shipped" anywhere else.
 */
export function forwardTransitions(status: string, allowed: string[]): string[] {
    return allowed.filter((next) => next !== 'shipped' && FORWARD.indexOf(next) > FORWARD.indexOf(status));
}
