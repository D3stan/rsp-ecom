const FORWARD = ['processing', 'shipped', 'delivered'];

export function forwardTransitions(status: string, allowed: string[]): string[] {
    return allowed.filter((next) => next !== 'shipped' && FORWARD.indexOf(next) > FORWARD.indexOf(status));
}
