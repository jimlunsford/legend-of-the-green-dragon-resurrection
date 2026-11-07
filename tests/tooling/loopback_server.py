"""Loopback PHP test servers: a port the OS chose, and a readiness wait that never falls through silently.

free_port() asks the OS for a port that is free now (bind to port 0). wait_until_ready() returns only when the server
process is running and answers on its port; if the process exits first, or nothing answers within the time allowed, it
raises ServerStartError with a clear message (and the server's own output, when the caller kept it), never a later
"connection refused" from the test. start_php_server() does both for a `php -S` server whose output goes to a file: it
also waits for PHP's own "started" line, so a different program that happens to listen on the port is never mistaken for
the server, and it tries a new port when PHP reports the port was taken in the moment between choosing and binding it.
"""
import socket
import subprocess
import time
from pathlib import Path


class ServerStartError(RuntimeError):
    pass


def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


def _tail(output):
    if output is None:
        return ''
    try:
        shown = Path(output).read_text(errors='replace').strip()
    except OSError:
        return ''
    return '\nServer output:\n' + shown[-2000:] if shown else ''


def wait_until_ready(server, port, name='Loopback PHP test server', timeout=10.0, output=None, started=None):
    """Waits until `server` (a Popen) is running and answers on 127.0.0.1:`port`. `output` is a file holding the
    server's output, quoted in the error; with `started`, that text must also be in the output first."""
    deadline = time.monotonic() + timeout
    while True:
        code = server.poll()
        if code is not None:
            raise ServerStartError(f'{name} on 127.0.0.1:{port} exited with code {code} before it answered.' + _tail(output))
        ready = started is None or started in Path(output).read_text(errors='replace')
        if ready:
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    if server.poll() is None:
                        return
            except OSError:
                pass
        if time.monotonic() >= deadline:
            raise ServerStartError(f'{name} on 127.0.0.1:{port} did not answer within {timeout:g} s.' + _tail(output))
        time.sleep(.05)


def start_php_server(arguments, root, cwd, env, output, name='Loopback PHP test server', attempts=3, timeout=10.0):
    """Starts `arguments` (php and its -d options) as `php -S` on a free port serving `root`, its output in the file
    `output`. Returns (process, port) once it answers; the caller terminates it. Raises ServerStartError otherwise."""
    output = Path(output)
    for attempt in range(1, attempts + 1):
        port = free_port()
        with output.open('w') as sink:
            server = subprocess.Popen(list(arguments) + ['-S', f'127.0.0.1:{port}', '-t', str(root)], cwd=cwd,
                                      stdout=sink, stderr=subprocess.STDOUT, env=env)
        try:
            wait_until_ready(server, port, name, timeout, output, started=f'(http://127.0.0.1:{port}) started')
            return server, port
        except ServerStartError:
            server.terminate()
            server.wait(timeout=10)
            if attempt < attempts and 'Address already in use' in output.read_text(errors='replace'):
                continue
            raise
    raise AssertionError('unreachable')
