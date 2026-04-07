<ul class="list-unstyled repo-tree-list mb-0">
    @foreach ($nodes as $node)
        <li class="repo-tree-node">
            <div class="d-flex align-items-center gap-2 py-1">
                <span class="badge {{ $node['type'] === 'tree' ? 'text-bg-warning' : 'text-bg-light border' }}">
                    {{ $node['type'] === 'tree' ? 'DIR' : 'FILE' }}
                </span>
                <code class="small">{{ $node['name'] }}</code>
            </div>

            @if ($node['children'] !== [])
                <div class="repo-tree-branch ms-3 ps-3">
                    @include('repositories.partials.tree', ['nodes' => $node['children']])
                </div>
            @endif
        </li>
    @endforeach
</ul>
