import os
from django.contrib.auth.decorators import login_required
from django.http import HttpResponse, Http404
from django.shortcuts import render_to_response, get_object_or_404
from django.template import RequestContext
from django.core.exceptions import ObjectDoesNotExist
from django.utils.encoding import smart_str
from django.views.decorators.http import require_POST
from django_ajax.decorators import ajax
from front.models import Camera, Image, EmailAddress
from sc_frontend import settings


@login_required
def index(request):
    camera_objects = Camera.objects.filter(user=request.user)
    camera_infos = []
    for camera in camera_objects:
        addresses_already_attached = camera.email_addresses.values_list('id', flat=True)
        addable_addresses = EmailAddress.objects.filter(user=request.user).exclude(id__in=list(addresses_already_attached)).values();
        print "Addable addresses for camera {}: {}".format(camera.id, addable_addresses)
        camera_info = {
            'camera': camera,
            'addable_mail_addresses': addable_addresses}
        camera_infos.append(camera_info)
    context = RequestContext(request, {
        'camera_infos': camera_infos,
    })
    return render_to_response('front/index.html', context)


@login_required
def latest_image(request, camera_id):
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    try:
        image = Image.objects.filter(camera=camera).latest('received')
    except ObjectDoesNotExist:
        raise Http404("Image not found.")

    filename = '%04d-%s.jpg' % (int(camera_id), image.received.strftime("%Y%m%d-%H%M%S"))
    filepath = os.path.join(settings.IMAGE_DIR, filename)
    response = HttpResponse(content_type='image/jpeg')
    response['X-Sendfile'] = smart_str(filepath)
    return response


@login_required
@require_POST
@ajax
def delete_mail_forward(request, camera_id, address_id):
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    address = camera.email_addresses.get(id=address_id)
    camera.email_addresses.remove(address)


