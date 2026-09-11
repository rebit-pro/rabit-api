#!/usr/bin/env bash
set -euo pipefail

fail() {
    printf '[swarm-data][error] %s\n' "$1" >&2
    exit 1
}

# This recipe only supports one local manager/data node. Never follow a remote context.
unset DOCKER_CONTEXT
readonly -a DOCKER_LOCAL=(docker --host unix:///var/run/docker.sock)
swarm="$("${DOCKER_LOCAL[@]}" info --format '{{.Swarm.LocalNodeState}} {{.Swarm.ControlAvailable}} {{.Swarm.NodeID}}')"
read -r state manager local_node <<< "$swarm"
[[ 'active' == "$state" && 'true' == "$manager" && -n "$local_node" ]] \
    || fail 'The SSH target must be an active local Swarm manager.'

nodes="$("${DOCKER_LOCAL[@]}" node ls --quiet)"
[[ "$local_node" == "$nodes" ]] \
    || fail 'Only a single-node Swarm on this SSH target is supported. Review actual data placement for other topologies.'

placement="$("${DOCKER_LOCAL[@]}" node inspect "$local_node" --format '{{.Status.State}} {{.Spec.Availability}} {{index .Spec.Labels "db"}}')"
[[ 'ready active db' == "$placement" ]] \
    || fail 'The local node must be ready, active, and labelled db=db.'

printf '[swarm-data] Single local manager/data node verified.\n' >&2
