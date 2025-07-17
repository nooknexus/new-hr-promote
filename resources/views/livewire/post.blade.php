<div class="card-body">
    <form wire:submit.prevent="createOrUpdatePost">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="exampleInputEmail1">Title</label>
                    <input type="text" class="form-control" id="title" placeholder="" wire:model.live="title">
                    @error('title')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    <label for="exampleInputPassword1">Content</label>
                    <input type="text" class="form-control" id="content" placeholder="" wire:model.live="content">
                    @error('content')
                    <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <div class="custom-file mb-3">

            <label for="formFileLg" class="form-label"></label>
            <input type="file" wire:model="photo">

        </div>
        {{-- Temporary preview before save --}}
        @if ($photo)
        <div class="mb-3">
            <p class="text-gray-700">Preview:</p>
            <img src="{{ $photo->temporaryUrl() }}" class="border rounded" width="100">
        </div>
        @endif
        {{-- Show saved image --}}
        @if ($savedPhotoPath)
        <div class="mb-3">
            <p class="text-gray-700">Saved Image:</p>
            <img src="{{ asset('storage/' . $savedPhotoPath) }}" class="border rounde" width="100">
        </div>
        @endif

        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"
            wire:target="createOrUpdatePost">Submit</button>

        <button class="btn btn-warning" wire:click="resetInputFields()">Cancel</button>

        <div wire:loading wire:target="createOrUpdatePost">
            Updating post...
        </div>
    </form>
    <table class="table mt-3">
        <thead class="thead-dark">
            <tr>
                <th scope="col">#</th>
                <th>Photo</th>
                <th scope="col">title</th>
                <th scope="col">content</th>
                <th scope="col">author</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($posts as $item)
            <tr>
                <td><img src="{{ $item->photo ? asset('storage/' . $item->photo) : '' }}" width="50"></td>
                <th scope="row">{{ $item->id }}</th>
                <td>{{ $item->title }}</td>
                <td>{{ $item->content }}</td>
                <td>{{ $item->author }}</td>
                <td><button type="button" class="btn btn-primary"
                        wire:click="editPost({{ $item->id }})">Edit</button>
                    <button type="button" class="btn btn-danger"
                        wire:click="deletePost({{ $item->id }})">Delete</button>
                </td>
            </tr>
            @empty
            <tr>
                <th colspan="4"> ไม่มีข้อมูล</th>

            </tr>
            @endforelse


        </tbody>
    </table>
    {{ $posts->links() }}

</div>
