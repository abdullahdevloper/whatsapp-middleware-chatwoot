<?php

namespace App\Services\FlowValidator;

class FlowValidator
{
    private const SUPPORTED_TYPES = ['message', 'menu', 'input', 'condition', 'action'];

    public function validate(array $definition): array
    {
        $errors = [];

        if (!isset($definition['start'])) {
            $errors[] = 'Missing start node.';
        }

        if (!isset($definition['nodes']) || !is_array($definition['nodes'])) {
            $errors[] = 'Missing nodes map.';
        }

        if (!empty($errors)) {
            return $errors;
        }

        $start = $definition['start'];
        $nodes = $definition['nodes'];

        if (!isset($nodes[$start])) {
            $errors[] = 'Start node does not exist.';
        }

        foreach ($nodes as $nodeId => $node) {
            $type = $node['type'] ?? null;
            if (!in_array($type, self::SUPPORTED_TYPES, true)) {
                $errors[] = "Unsupported type for node {$nodeId}.";
                continue;
            }

            if ($type === 'menu') {
                $options = $node['options'] ?? [];
                if (!is_array($options) || empty($options)) {
                    $errors[] = "Menu node {$nodeId} has no options.";
                } else {
                    foreach ($options as $target) {
                        if (!isset($nodes[$target])) {
                            $errors[] = "Menu node {$nodeId} points to missing node {$target}.";
                        }
                    }
                }
            }

            if ($type === 'condition') {
                $trueNode = $node['true'] ?? null;
                $falseNode = $node['false'] ?? null;
                if (!isset($nodes[$trueNode])) {
                    $errors[] = "Condition node {$nodeId} true target missing.";
                }
                if (!isset($nodes[$falseNode])) {
                    $errors[] = "Condition node {$nodeId} false target missing.";
                }
            }

            if ($type === 'action' || $type === 'input') {
                $next = $node['next'] ?? null;
                if ($next !== null && !isset($nodes[$next])) {
                    $errors[] = ucfirst($type) . " node {$nodeId} next target missing.";
                }
            }
        }

        foreach ($nodes as $nodeId => $node) {
            $type = $node['type'] ?? null;
            $directTargets = [];

            if ($type === 'menu') {
                $directTargets = array_values($node['options'] ?? []);
            } elseif ($type === 'condition') {
                $directTargets = array_filter([$node['true'] ?? null, $node['false'] ?? null]);
            } elseif ($type === 'action') {
                $directTargets = array_filter([$node['next'] ?? null]);
            }

            if (in_array($nodeId, $directTargets, true)) {
                $errors[] = "Direct loop detected at node {$nodeId}.";
            }
        }

        return $errors;
    }
}
