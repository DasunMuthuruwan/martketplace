// Fresh browser-test database before the server starts serving the tests.
import { prepare } from './environment.mjs';

export default function globalSetup() {
    prepare();
}
